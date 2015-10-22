<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Observer\Cache\Adapter;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
interface CacheAdapterInterface
{
    /**
     * @param string $key The cache key
     *
     * @return bool True if the key exists and is not expired
     */
    public function has($key);

    /**
     * @param string $key The cache key
     *
     * @return mixed The value for the selected key
     */
    public function get($key);

    /**
     * @param string   $key   The cache key
     * @param mixed    $value The value
     * @param null|int $ttl   The time to live in second, must be greater than zero. Let NULL if no expiration.
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null);
}
 