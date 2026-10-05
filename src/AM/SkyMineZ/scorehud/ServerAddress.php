<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;
use pocketmine\Server;

/**
 * "ip:port", with the port left out when it is the default one. Used by the
 * sidebar and by every leaderboard footer.
 */
final class ServerAddress
{
    /**
     * The address never changes while the server runs (IP and port are fixed
     * at startup), so it is resolved once and reused by every sidebar pass and
     * every leaderboard footer instead of re-reading server properties per
     * player.
     */
    private static ?string $cached = null;

    private function __construct()
    {
    }

    public static function of(
        Main $main
    ): string {
        return self::fromServer(
            $main->getServer()
        );
    }

    /**
     * The IPv4 address is used because that is what players copy into their
     * client; IPv6 literals confuse most of them.
     */
    public static function fromServer(
        Server $server
    ): string {
        if (self::$cached === null) {
            $ip = $server->getIp();
            $port = $server->getPort();

            self::$cached = (
                $port <= 0
                || $port === Server::DEFAULT_PORT_IPV4
            )
                ? $ip
                : $ip . ':' . $port;
        }

        return self::$cached;
    }
}