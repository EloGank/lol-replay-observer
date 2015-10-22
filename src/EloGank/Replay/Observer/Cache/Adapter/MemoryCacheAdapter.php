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
class MemoryCacheAdapter implements CacheAdapterInterface
{
    /**
     * @var array
     */
    protected $spool = [];


    /**
     * @param string $key The cache key
     *
     * @return bool True if the key exists and is not expired
     */
    public function has($key)
    {
        return array_key_exists($key, $this->spool) && $this->spool[$key]['deleted_at'] > time();
    }

    /**
     * @param string $key The cache key
     *
     * @return mixed The value for the selected key
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->spool[$key]['value'];
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
        if (!is_int($ttl) || 0 >= $ttl) {
            throw new \InvalidArgumentException('The time to live parameter must be an integer and greater than zero.');
        }

        $this->spool[$key] = [
            'value'      => $value,
            'deleted_at' => time() + $ttl
        ];

        return true;
    }
}
