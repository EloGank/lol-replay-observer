<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NEloGank\Replay\Observer;

use NEloGank\Replay\Downloader\Client\Exception\TimeoutException;
use NEloGank\Replay\Downloader\Client\ReplayClient;
use NEloGank\Replay\Observer\Cache\Adapter\CacheAdapterInterface;
use NEloGank\Replay\Observer\Client\Exception\ReplayChunkNotFoundException;
use NEloGank\Replay\Observer\Client\Exception\ReplayEndStatsNotFoundException;
use NEloGank\Replay\Observer\Client\Exception\ReplayKeyframeNotFoundException;
use NEloGank\Replay\Observer\Client\ReplayObserverClient;
use NEloGank\Replay\Observer\Exception\UnauthorizedAccessException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayObserver implements LoggerAwareInterface
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
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @param ReplayObserverClient  $observerClient
     * @param CacheAdapterInterface $cache
     * @param ReplayClient|null     $apiClient
     * @param bool                  $isAuthStrict
     */
    public function __construct(
        ReplayObserverClient $observerClient,
        CacheAdapterInterface $cache,
        ReplayClient $apiClient = null,
        $isAuthStrict = false
    )
    {
        if (false === $isAuthStrict && null == $cache) {
            throw new \RuntimeException('The replay observer cannot be "authorization strict" with an empty cache.');
        }

        if (null == $apiClient) {
            $apiClient = new ReplayClient();
        }

        $this->observerClient = $observerClient;
        $this->cache          = $cache;
        $this->apiClient      = $apiClient;
        $this->isAuthStrict   = $isAuthStrict;
        $this->headers        = null;
    }

    /**
     * @param string|null $acceptHeader The header 'Accept' data
     *
     * @return string
     *
     * @throws UnauthorizedAccessException
     * @throws TimeoutException
     */
    public function getVersion($acceptHeader = null)
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
     * @param string $region
     * @param int    $gameId
     * @param int    $token
     * @param string $clientIp
     *
     * @return array
     *
     * @throws Client\Exception\ReplayFolderNotFoundException
     */
    public function getGameMetasData($region, $gameId, $token, $clientIp)
    {
        // Setting cache for chunk init
        $cacheName = $this->getCacheName($gameId, $clientIp);
        $this->cache->set($cacheName, 0, 86400); // 1 day

        return $this->observerClient->getMetas($region, $gameId);
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param int    $chunkId
     * @param string $clientIp
     *
     * @return array|bool
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
     * @param string $region
     * @param int    $gameId
     * @param int    $chunkId
     *
     * @return null|string
     *
     * @throws ReplayChunkNotFoundException
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
     * @param string $region
     * @param int    $gameId
     *
     * @return null|string
     *
     * @throws ReplayEndStatsNotFoundException
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
        if (null != $this->logger) {
            $this->logger->info('Game #' . $gameId . ': ' . $message);
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
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
