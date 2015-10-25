<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Observer;

use EloGank\Replay\Downloader\Client\Exception\TimeoutException;
use EloGank\Replay\Downloader\Client\ReplayClient;
use EloGank\Replay\Observer\Cache\Adapter\CacheAdapterInterface;
use EloGank\Replay\Observer\Cache\Adapter\NullCacheAdapter;
use EloGank\Replay\Observer\Client\Exception\ReplayChunkNotFoundException;
use EloGank\Replay\Observer\Client\Exception\ReplayEndStatsNotFoundException;
use EloGank\Replay\Observer\Client\Exception\ReplayKeyframeNotFoundException;
use EloGank\Replay\Observer\Client\ReplayObserverClient;
use EloGank\Replay\Observer\Exception\UnauthorizedAccessException;
use EloGank\Replay\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayObserver
{
    const CACHE_KEY = 'elogank.replay.observer.';


    /**
     * @var ReplayClient
     */
    protected $apiClient;

    /**
     * @var ReplayObserverClient
     */
    protected $observerClient;

    /**
     * @var CacheAdapterInterface
     */
    protected $cache;

    /**
     * @var bool
     */
    protected $isAuthStrict;

    /**
     * @var OutputInterface
     */
    protected $output;


    /**
     * @param ReplayObserverClient       $observerClient
     * @param ReplayClient|null          $apiClient
     * @param CacheAdapterInterface|null $cache
     * @param bool                       $isAuthStrict
     */
    public function __construct(
        ReplayObserverClient $observerClient,
        ReplayClient $apiClient = null,
        CacheAdapterInterface $cache = null,
        $isAuthStrict = false
    )
    {
        if (false === $isAuthStrict && null == $cache) {
            throw new \RuntimeException('The replay observer cannot be "authorization strict" with an empty cache.');
        }

        if (null == $apiClient) {
            $apiClient = new ReplayClient();
        }

        if (null == $cache) {
            $cache = new NullCacheAdapter();
        }

        $this->observerClient = $observerClient;
        $this->apiClient      = $apiClient;
        $this->cache          = $cache;
        $this->isAuthStrict   = $isAuthStrict;
        $this->headers        = null;
    }

    /**
     * Route: /version
     *
     * @param null $acceptHeader
     *
     * @return string
     *
     * @throws UnauthorizedAccessException
     * @throws TimeoutException
     */
    public function versionAction($acceptHeader = null)
    {
        // Retrieve and cache the version
        $cacheName = 'replay.version';

        if ($this->cache->has($cacheName)) {
            // Cache the version to avoid a next call
            $version = $this->cache->get($cacheName);
        } else {
            $version = $this->apiClient->getObserverVersion();

            if (false === $version) {
                throw new TimeoutException('The server has timed out.');
            }

            $this->cache->set($cacheName, $version, 86400); // 1 day
        }

        if (!$this->isAuthorized($acceptHeader)) {
            throw new UnauthorizedAccessException();
        }

        return $version;
    }

    /**
     * Route: /getGameMetaData/{region}/{gameId}/{token}/token
     */
    public function getGameMetasData($region, $gameId, $token, $clientIp)
    {
        // Setting cache for chunk init
        $cacheName = $this->getCacheName($gameId, $clientIp);
        $this->cache->set($cacheName, 0, 86400); // 1 day

        return $this->observerClient->getMetas($region, $gameId);
    }

    /**
     * Route: /getLastChunkInfo/{region}/{gameId}/{chunkId}/token
     */
    public function getLastChunkInfo($region, $gameId, $chunkId, $clientIp)
    {
        // Cache control
        $cacheName = $this->getCacheName($gameId, $clientIp);

        // TODO validate isAuthStrict with a same IP
        if ($this->isAuthStrict && !$this->cache->has($cacheName)) {
            // Log
            $this->log($gameId, sprintf('GET /getLastChunkInfo/%s/%s/%d/token | ERROR: trying to access without cached current chunkId', $region, $gameId, $chunkId));

            return false;
        }

        // TODO at this time, two players with the same IP watching the same game will have some weird issue
        // FIXME test if $chunkId is the same as this value
        $currentChunkId = $this->cache->get($cacheName) + 1;

        // Saving current chunkId in cache
        $this->cache->set($cacheName, $currentChunkId);

        $keyframeId = null;
        $lastChunkInfo = $this->observerClient->getLastChunkInfo($region, $gameId, $currentChunkId, $keyframeId);

        // Log
        $this->log($gameId, sprintf('GET /getLastChunkInfo/%s/%s/%d/token | currentChunkId: %s | currentKeyframe: %s', $region, $gameId, $chunkId, $currentChunkId, $keyframeId));

        return $lastChunkInfo;
    }

    /**
     * Route: /getGameDataChunk/{region}/{gameId}/{chunkId}/token
     */
    public function getGameDataChunkPath($region, $gameId, $chunkId)
    {
        $chunkPath = null;

        try {
            $chunkPath = $this->observerClient->getChunkPath($region, $gameId, $chunkId);
        } catch (ReplayChunkNotFoundException $e) {
            // Log
            $this->log($gameId, sprintf('GET /getGameDataChunk/%s/%s/%d/token | ERROR: the chunk data is not found', $region, $gameId, $chunkId));

            throw $e;
        }

        // Log
        $this->log($gameId, sprintf('GET /getGameDataChunk/%s/%s/%d/token', $region, $gameId, $chunkId));

        // TODO return createDownloadResponse
        return $chunkPath;
    }

    /**
     * @param string $region
     * @param string $gameId
     * @param int    $chunkId
     *
     * @return string
     *
     * @throws ReplayChunkNotFoundException
     */
    public function getGameDataChunkContent($region, $gameId, $chunkId)
    {
        return file_get_contents($this->getGameDataChunkPath($region, $gameId, $chunkId));
    }

    /**
     * Route: /getKeyFrame/{region}/{gameId}/{keyframeId}/token
     */
    public function getKeyframePath($region, $gameId, $keyframeId)
    {
        $keyframePath = null;

        try {
            $keyframePath = $this->observerClient->getKeyframePath($region, $gameId, $keyframeId);
        } catch (ReplayKeyframeNotFoundException $e) {
            // Log
            $this->log($gameId, sprintf('GET /getKeyFrame/%s/%s/%d/token | ERROR: the keyframe is not found', $region, $gameId, $keyframeId));

            throw $e;
        }

        // Log
        $this->log($gameId, sprintf('GET /getKeyFrame/%s/%s/%d/token', $region, $gameId, $keyframeId));

        // TODO return createDownloadResponse
        return $keyframePath;
    }

    /**
     * @param string $region
     * @param string $gameId
     * @param int    $keyframeId
     *
     * @return string
     *
     * @throws ReplayKeyframeNotFoundException
     */
    public function getKeyframeContent($region, $gameId, $keyframeId)
    {
        return file_get_contents($this->getKeyframePath($region, $gameId, $keyframeId));
    }

    /**
     * Route: /endOfGameStats/{region}/{gameId}/null
     */
    public function getEndOfGameStatsPath($region, $gameId)
    {
        $endStatsPath = null;

        try {
            $endStatsPath = $this->observerClient->getEndStats($region, $gameId);
        } catch (ReplayEndStatsNotFoundException $e) {
            // Log
            $this->log($gameId, sprintf('GET /endOfGameStats/%s/%s/null | ERROR: the endstats is not found', $region, $gameId));

            throw $e;
        }

        // Log
        $this->log($gameId, sprintf('GET /endOfGameStats/%s/%s/null', $region, $gameId));

        // TODO return createDownloadResponse($endStatsPath, 'null', true)
        return $endStatsPath;
    }

    /**
     * @param string $region
     * @param string $gameId
     *
     * @return string
     *
     * @throws ReplayEndStatsNotFoundException
     */
    public function getEndOfGameStatsContent($region, $gameId)
    {
        return file_get_contents($this->getEndOfGameStatsPath($region, $gameId));
    }

    /**
     * @param string $gameId
     * @param string $message
     *
     * @return bool
     */
    protected function log($gameId, $message)
    {
        if (null != $this->output && OutputInterface::VERBOSITY_VERBOSE >= $this->output->getVerbosity()) {
            $this->output->writeln('Game #' . $gameId . ': ' . $message);
        }
    }

    /**
     * @param int    $gameId
     * @param string $clientIp
     *
     * @return string
     */
    protected function getCacheName($gameId, $clientIp)
    {
        return $cacheName = self::CACHE_KEY . $gameId . '.ip.' . $clientIp . '.chunk_infos.try';
    }

    /**
     * This method will check if the user does not leech your replay files.
     * The only condition is to check if the user header send the "Accept" header. Indeed, the game does not send
     * this data.
     *
     * @param null|string $acceptHeader
     *
     * @return bool
     */
    public function isAuthorized($acceptHeader)
    {
        return !$this->isAuthStrict || null === $acceptHeader;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
