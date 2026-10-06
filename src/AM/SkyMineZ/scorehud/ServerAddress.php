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

    private static string $cachedFor = '';

    private function __construct()
    {
    }

    public static function invalidate(): void
    {
        self::$cached = null;
        self::$cachedFor = '';
    }

    public static function of(
        Main $main
    ): string {
        /*
         * An explicit address wins over auto-detection: servers behind a proxy
         * or with a memorable hostname show that instead of a raw IP.
         */
        $override = trim(
            $main->getConfigManager()->getString('server.address-override')
        );

        if ($override !== '') {
            return $override;
        }

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
        $key = $server->getIp() . ':' . $server->getPort();

        if (self::$cached === null || self::$cachedFor !== $key) {
            $ip = $server->getIp();
            $port = $server->getPort();

            self::$cached = (
                $port <= 0
                || $port === Server::DEFAULT_PORT_IPV4
            )
                ? $ip
                : $ip . ':' . $port;
            self::$cachedFor = $key;
        }

        return self::$cached;
    }
}