<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Observer\Cache;

use EloGank\Replay\Observer\Cache\Adapter\CacheAdapterInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class RedisCacheAdapter implements CacheAdapterInterface
{
    /**
     * @var mixed
     */
    protected $redis;


    /**
     * @param mixed $redis
     */
    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    /**
     * @param string $key The cache key
     *
     * @return bool True if the key exists and is not expired
     */
    public function has($key)
    {
        return $this->redis->exists($key);
    }

    /**
     * @param string $key The cache key
     *
     * @return mixed The value for the selected key
     */
    public function get($key)
    {
        return $this->redis->get($key);
    }

    /**
     * @param string   $key   The cache key
     * @param mixed    $value The value
     * @param null|int $ttl   The time to live in second, must be greater than zero. Let NULL if no expiration.
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null)
    {
        if (null == $ttl) {
            return $this->redis->set($key, $value);
        }

        if (!is_int($ttl) || 0 >= $ttl) {
            throw new \InvalidArgumentException('The time to live parameter must be an integer and greater than zero.');
        }

        return $this->redis->setex($key, $value, $ttl);
    }
}
 