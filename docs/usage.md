League of Legends Replay Observer (player)
====================================

## Service

The service can be instancied like this :

``` php
$service = new \EloGank\Replay\Observer\ReplayObserver(
    new \EloGank\Replay\Observer\ReplayObserver\Client\ReplayObserverClient($replaysDirpath),
    new \EloGank\Replay\Observer\ReplayObserver\Cache\Adapter\RedisCacheAdapter($redisConnection)
);
```

## Routes

**TL;DR :** see this Silex example controller for all routes and services calls : https://github.com/EloGank/lol-replay-observer-silex/blob/master/src/EloGank/Replay/Observer/Controller/ObserverController.php

All routes must be prefixed by `/observer-mode/rest/consumer` (i.e: `http://www.foobar.com/observer-mode/rest/consumer/version`, but to be clear, I'll repeat this prefix in all listed routes below.

### `/version`

`/observer-mode/rest/consumer/version`

Returns the observer server version.  
This response can easilly be cached for one day.

**Response :** `plain/text`

**Service :** `\EloGank\Replay\Observer\ReplayObserver::version($acceptHeader)`  
**Service parameters :**

| `$acceptHeader` |
| ------- |
| string or null |
| The HTTP request header "Accept" data |

### `/getGameMetaData`

`/observer-mode/rest/consumer/getGameMetaData/{region}/{gameId}/{token}/token`

Return the content of the replay `metas.json` file.

**Response :** `application/json`  
**Request parameters :**

| region | gameId | token |
| ------- | --------- | ------- |
| string | long | int |
| The spectator region | The game id | A token sent by the client

**Service :** `\EloGank\Replay\Observer\ReplayObserver::getGameMetasData($region, $gameId, $token, $clientIp)`  
**Service parameters :**

| `$region` | `$gameId` | `$token` | `$clientIp` |
| ------- | ------- | ------- | ------- |
| string | long | int | string |
| The region from the URI parameters | The game id from the URI parameters | The token from the URI parameters | The client request IP address

### `/getLastChunkInfo`

`/observer-mode/rest/consumer/getLastChunkInfo/{region}/{gameId}/{chunkId}/token`

Return the last available chunk. See `/getGameMetaData` below for more information.

**Response :** `application/json`  
**Request parameters :**

| region | gameId | chunkId |
| ------- | --------- | ------- |
| string | long | int |
| The spectator region | The game id | The current downloaded chunk id

**Service :** `\EloGank\Replay\Observer\ReplayObserver::getLastChunkInfo($region, $gameId, $chunkId, $clientIp)`  
**Service parameters :**

| `$region` | `$gameId` | `$chunkId` | `$clientIp` |
| ------- | ------- | ------- | ------- |
| string | long | int | string |
| The region from the URI parameters | The game id from the URI parameters | The chunk id from the URI parameters | The client request IP address

### `/getGameDataChunk`

`/observer-mode/rest/consumer/getGameDataChunk/{region}/{gameId}/{chunkId}/token`

Return the last available chunk data as HTTP downloadable content (last 30 seconds of the game).  
You must understand that the official replay system has been built for a live game, so each 30 seconds, the game asks for data. So this route will be called each 30 seconds, returning each chunk data.  
But here, the context is not the same as a live game : we already have all replay data, so this method will be called every ~100 ms.

**Response :** `application/octet-stream`  
**Request parameters :**

| region | gameId | chunkId |
| ------- | --------- | ------- |
| string | long | int |
| The spectator region | The game id | The current downloaded chunk id

**Service :** `\EloGank\Replay\Observer\ReplayObserver::getGameDataChunkPath($region, $gameId, $chunkId)`  
**Service parameters :**

| `$region` | `$gameId` | `$chunkId` |
| ------- | ------- | ------- |
| string | long | int |
| The region from the URI parameters | The game id from the URI parameters | The chunk id from the URI parameters

### `/getKeyFrame`

`/observer-mode/rest/consumer/getKeyFrame/{region}/{gameId}/{keyframeId}/token`

Return the last available keyframe data as HTTP downloadable content. The keyframe summarizes the last minute of the game (only main information). Delivered each minute on live game. Here, each ~200 ms.

**Response :** `application/octet-stream`  
**Request parameters :**

| region | gameId | keyframeId |
| ------- | --------- | ------- |
| string | long | int |
| The spectator region | The game id | The current downloaded keyframe id

**Service :** `\EloGank\Replay\Observer\ReplayObserver::getKeyframePath($region, $gameId, $keyframeId)`  
**Service parameters :**

| `$region` | `$gameId` | `$keyframeId` |
| ------- | ------- | ------- |
| string | long | int |
| The region from the URI parameters | The game id from the URI parameters | The keyframe id from the URI parameters

### `/endOfGameStats`

`/observer-mode/rest/consumer/endOfGameStats/{region}/{gameId}/null`

Return the replayed game data as HTTP downloadable content. This is used only after a live game. This is the screen which summarizes the game, appears just after the "Victory" or "Defeat" title.

**Response :** `application/octet-stream`  
**Request parameters :**

| region | gameId |
| ------- | --------- |
| string | long |
| The spectator region | The game id |

**Service :** `\EloGank\Replay\Observer\ReplayObserver::getEndOfGameStatsAction($region, $gameId)`  
**Service parameters :**

| `$region` | `$gameId` |
| ------- | ------- |
| string | long | int |
| The region from the URI parameters | The game id from the URI parameters
