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
     * @param null                       $client
     * @param                            $replayObserverClient
     * @param CacheAdapterInterface|null $cache
     * @param bool|false                 $isAuthStrict
     *
     * // TODO handle the case of changing the $replayObserverClient class
     */
    public function __construct($client = null, $replayObserverClient, CacheAdapterInterface $cache = null, $isAuthStrict = false)
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
    public function gameMetasDataAction($region, $gameId, $token, $clientIp)
    {
        // Setting cache for chunk init
        $cacheName = $this->getCacheName($gameId, $clientIp);
        $this->cache->set($cacheName, 0, 86400); // 1 day

        return $this->replayObserverClient->getMetas($region, $gameId);
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
}
