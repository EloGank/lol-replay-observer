<?php

/*
 * This file is part of the "EloGank League of Legends Replay Observer" package.
 *
 * https://github.com/EloGank/lol-replay-observer
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Observer\Client;

use EloGank\Replay\Observer\Client\Exception\ReplayFolderNotFoundException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayObserverClient
{
    /**
     * @var string
     */
    protected $replaysDirPath;

    /**
     * @param string $replaysDirPath
     */
    public function __construct($replaysDirPath)
    {
        $this->replaysDirPath = $replaysDirPath;
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return array
     *
     * @throws ReplayFolderNotFoundException
     */
    public function getMetas($region, $gameId)
    {
        // Check if exists, if no disable it
        if (!is_dir($this->getReplayDirPath($region, $gameId))) {
            throw new ReplayFolderNotFoundException(
                'The replay folder #' . $gameId . ' is not found. The replay will be disabled'
            );
        }

        return json_decode(file_get_contents($this->getReplayDirPath($region, $gameId) . '/metas.json'), true);
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return string
     */
    protected function getReplayDirPath($region, $gameId)
    {
        $stringGameId = (string) $gameId;

        return sprintf(
            '%s/%s/%s/%s/%s/%s',
            $this->replaysDirPath,
            $region,
            $stringGameId[0] . $stringGameId[1],
            $stringGameId[2],
            $stringGameId[3],
            $gameId
        );
    }
}
