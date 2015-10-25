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

use EloGank\Replay\Observer\Client\Exception\ReplayChunkNotFoundException;
use EloGank\Replay\Observer\Client\Exception\ReplayEndStatsNotFoundException;
use EloGank\Replay\Observer\Client\Exception\ReplayFolderNotFoundException;
use EloGank\Replay\Observer\Client\Exception\ReplayKeyframeNotFoundException;

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
     * @param array $metas
     * @param int   $chunkId
     * @param bool  $throwException
     *
     * @return int
     *
     * @throws ReplayKeyframeNotFoundException
     */
    public function findKeyframeByChunkId(array $metas, $chunkId, $throwException = false)
    {
        // Method based on metas.json > pendingAvailableKeyFrameInfo
        foreach ($metas['pendingAvailableKeyFrameInfo'] as $keyframe) {
            if ($chunkId == $keyframe['nextChunkId']) {
                return $keyframe['id'];
            }
        }

        if ($throwException) {
            throw new ReplayKeyframeNotFoundException('No keyframe found for chunk #' . ($chunkId + 1));
        }

        return $this->findKeyframeByChunkId($metas, $chunkId - 1, true);
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
    public function getChunkPath($region, $gameId, $chunkId)
    {
        $filePath = $this->getReplayDirPath($region, $gameId) . '/chunks/' . $chunkId;

        if (!is_file($filePath)) {
            throw new ReplayChunkNotFoundException('The chunk #' . $chunkId . ' is not found');
        }

        return $filePath;
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
    public function getKeyframePath($region, $gameId, $keyframeId)
    {
        $filePath = $this->getReplayDirPath($region, $gameId) . '/keyframes/' . $keyframeId;

        if (!is_file($filePath)) {
            throw new ReplayKeyframeNotFoundException('The keyframe #' . $keyframeId . ' is not found');
        }

        return $filePath;
    }

    /**
     * @param string $region
     * @param string $gameId
     * @param int    $currentChunkId
     * @param int    $keyframeId     Passed in reference to be dumped for debug purpose
     *
     * @return array
     *
     * @throws ReplayFolderNotFoundException
     * @throws ReplayKeyframeNotFoundException
     */
    public function getLastChunkInfo($region, $gameId, $currentChunkId, &$keyframeId = null)
    {
        $metas = $this->getMetas($region, $gameId);

        // Game loading information
        //$firstChunkId = $replayManager->findFirstChunkId($metas);
        $firstChunkId = $metas['firstChunkId'];

        // A bug appears when endStartupChunkId = 3 and startGameChunkId = 5, the game won't load
        if ($metas['endStartupChunkId'] + 2 == $firstChunkId) {
            $firstChunkId = $metas['startGameChunkId'] + 2;
        }

        $keyframeId = $this->findKeyframeByChunkId($metas, $firstChunkId);

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
            $keyframeId = $this->findKeyframeByChunkId($metas, $currentChunkId);

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

        return $lastChunkInfo;
    }

    /**
     * @param string $region
     * @param string $gameId
     *
     * @return string
     *
     * @throws ReplayEndStatsNotFoundException
     */
    public function getEndStats($region, $gameId)
    {
        $filePath = $this->getReplayDirPath($region, $gameId) . '/endstats';

        if (!is_file($filePath)) {
            throw new ReplayEndStatsNotFoundException('The endstats for game #' . $gameId . ' is not found');
        }

        return $filePath;
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
