<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Observer\Exception;

use EloGank\Replay\Exception\ReplayException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class UnauthorizedAccessException extends ReplayException
{
    /**
     * @param string $message
     * @param int    $statusCode
     */
    public function __construct($message = 'Unauthorized user access', $statusCode = 0)
    {
        parent::__construct($message, $statusCode);
    }
}
