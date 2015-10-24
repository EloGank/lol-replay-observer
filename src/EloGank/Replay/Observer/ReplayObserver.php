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
    protected $client;

    /**
     * @var ReplayObserverClient
     */
    protected $replayObserverClient;

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
     * @param null                       $client
     * @param                            $replayObserverClient
     * @param CacheAdapterInterface|null $cache
     * @param bool|false                 $isAuthStrict
     *
     * // TODO handle the case of changing the $replayObserverClient class
     */
    public function __construct(
        $client = null,
        $replayObserverClient,
        CacheAdapterInterface $cache = null,
        $isAuthStrict = false
    )
    {
        if (false === $isAuthStrict && null == $cache) {
            throw new \RuntimeException('The replay observer cannot be "authorization strict" with an empty cache.');
        }

        if (null == $client) {
            $client = new ReplayClient();
        }

        if (null == $cache) {
            $cache = new NullCacheAdapter();
        }

        $this->client       = $client;
        $this->cache        = $cache;
        $this->isAuthStrict = $isAuthStrict;
        $this->headers      = null;
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
            $version = $this->client->getObserverVersion();
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

        return $this->replayObserverClient->getMetas($region, $gameId);
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
        $currentChunkId = $this->cache->get($cacheName) + 1;
        $metas = $this->replayObserverClient->getMetas($region, $gameId);

        // Game loading information
        //$firstChunkId = $replayManager->findFirstChunkId($metas);
        $firstChunkId = $metas['firstChunkId'];

        // A bug appears when endStartupChunkId = 3 and startGameChunkId = 5, the game won't load
        if ($metas['endStartupChunkId'] + 2 == $firstChunkId) {
            $firstChunkId = $metas['startGameChunkId'] + 2;
        }

        $keyframeId = $this->replayObserverClient->findKeyframeByChunkId($metas, $firstChunkId);

        $lastChunkInfo = array(
            'chunkId'            => $firstChunkId,
            'availableSince'     => 30000,
            'nextAvailableChunk' => 30000,
            'keyFrameId'         => $keyframeId,
            'nextChunkId'        => $firstChunkId,
            'endStartupChunkId'  => $metas['endStartupChunkId'],
            'startGameChunkId'   => $metas['startGameChunkId'],
            'endGameChunkId'     => 0,
            'duration'           => 30000
        );

        // In game information
        if ($firstChunkId != $metas['startGameChunkId'] && $currentChunkId - 1 == $metas['startGameChunkId']) {
            $currentChunkId = $firstChunkId;
        }

        if ($currentChunkId > $metas['startGameChunkId']) {
            $keyframeId = $this->replayObserverClient->findKeyframeByChunkId($metas, $currentChunkId);
            if ($currentChunkId > $metas['lastChunkId']) {
                $currentChunkId = $metas['lastChunkId'];
            }

            $lastChunkInfo['chunkId']            = $currentChunkId;
            $lastChunkInfo['nextChunkId']        = $metas['lastChunkId'];
            $lastChunkInfo['keyFrameId']         = $keyframeId;
            $lastChunkInfo['nextAvailableChunk'] = $currentChunkId == $firstChunkId + 6 ? 30000 : 100; // wait for full loading
        }

        // End game, stop downloading
        if ($currentChunkId == $metas['lastChunkId']) {
            $lastChunkInfo['nextAvailableChunk'] = 90000;
            $lastChunkInfo['endGameChunkId']     = $metas['endGameChunkId'];
        }

        // Saving current chunkId in cache
        $this->cache->set($cacheName, $currentChunkId);

        // Log
        $this->log($gameId, sprintf('GET /getLastChunkInfo/%s/%s/%d/token | currentChunkId: %s | currentKeyframe: %s', $region, $gameId, $chunkId, $currentChunkId, $keyframeId));

        return $lastChunkInfo;
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
