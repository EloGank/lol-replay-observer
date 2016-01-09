League of Legends Replay Observer (player)
====================================

This project provides you a way to **watch your downloaded replays directly in the League of Legend official client** like replay.gg or op.gg feature.

**Please note that is the library only** : you need to connect it with your application *(controller)* and call all available services for each routes. Replays must be downloaded with the https://github.com/EloGank/lol-replay-downloader library *(or the CLI built-in solution)*. See the documentation for more information.  
**If you want a built-in solution of this library**, please see the repository : https://github.com/EloGank/lol-replay-observer-silex.

## Features

* **A fast way to download replays data** (a 40min replay length is downloaded in ~1 minute).
* Avoid a "Bug Slat" on the beginning of the game when the computer has poor performances.
* **Easily extendable**.
* Monolog ready.

## Installation

### Requirements

For now, **this library requires [Redis](http://redis.io/)**.  
Maybe, in the future, this requirement will be optional.

### Composer

In your project, in the folder where the `composer.json` file is located, run :

``` bash
composer require elogank/lol-replay-observer
```

[What is Composer ?](https://getcomposer.org)

### Manually

Clone this repository or [download the full zipped library](https://github.com/EloGank/lol-replay-downloader/archive/master.zip).

## Configuration

Be sure to have a Virtual Host *(Apache, NGINX, or other web servers)* to handle clients requests.  
All your routes must have the prefix `/observer-mode/rest/consumer`.

See usage part below for more informations about routes configuration.

## Usage

See the [usage dedicated documentation](./docs/usage.md) for more information.

## Client configuration

To connect to your client to watch your replays, you have to retrieve some data :

* Your domain name or IP.
* The spectated region *(this is not the same as the game region)*.
* The game id.
* The game encryption key.

All are available in the `metas.json` in the downloaded replay data folder, for example :

``` json
/* metas.json */
{
    "gameKey": {
        "gameId": 1234567890,
        "platformId": "EUW1" /* region */
    },
    "gameServerAddress": "...",
    "port": ...,
    "encryptionKey": "zfBsWycQuDkkDNJhwSzdIYAmsAJu0n2s",
    ...
}
```

Then, run this command *(on Windows, run ![](http://res1.windows.microsoft.com/resbox/en/6.3/main/aa922834-ed43-40f1-8830-d5507badb56c_39.jpg) + R)* by replacing the 4 variables above and the location or your game directory :

```
"C:\Riot Games\RADS\solutions\lol_game_client_sln\releases\0.0.1.113\deploy\League of Legends.exe" "8394" "LoLLauncher.exe" "" "spectator [YOUR_DOMAIN_NAME]:80 [ENCRYPTION_KEY] [GAME_ID] [REGION]"
```

Example with my `metas.json` file data above :

```
"C:\Riot Games\RADS\solutions\lol_game_client_sln\releases\0.0.1.113\deploy\League of Legends.exe" "8394" "LoLLauncher.exe" "" "spectator www.foobar.com:80 zfBsWycQuDkkDNJhwSzdIYAmsAJu0n2s 1234567890 EUW1"
```

**Notes :**

* The `0.0.1.113` folder name can change when the game is updated. Be sure to update it for your users.
* The port (`:80`) is very important here, do not remove it. Of course, you can change it if your website is running on another port.
* You can found some batch files to automatically run the game on lolking.net, op.gg, or other websites that has that feature.

## Important notes

According to the new Riot Terms of Use *(1st October 2014)*, using data from another source of their official API is **not** allowed. So using data by parsing replays decoded files is not allowed.

## Reporting an issue or a feature request

Feel free to open an issue, fork this project or suggest an awesome new feature in the [issue tracker](https://github.com/EloGank/lol-replay-observer/issues).  

## Known issues

* The first minute of the game can be unreachable on some replays.

## Credit

See the list of [contributors](https://github.com/EloGank/lol-replay-observer/graphs/contributors).

## Related projects

See the [EloGank organization](https://github.com/EloGank) to have the full list of related projects the League of Legend game.

## Licence

[MIT, more information](./LICENSE)

*This repository isn't endorsed by Riot Games and doesn't reflect the views or opinions of Riot Games or anyone officially involved in producing or managing League of Legends.  
League of Legends and Riot Games are trademarks or registered trademarks of Riot Games, Inc. League of Legends (c) Riot Games, Inc.*
