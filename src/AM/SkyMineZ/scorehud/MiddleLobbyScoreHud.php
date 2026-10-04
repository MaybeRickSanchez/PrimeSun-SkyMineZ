<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;

class MiddleLobbyScoreHud
{
    public function __construct(
        private Main $main
    ) {
    }

    public function getTitle(): string
    {
        return '§dSkyMine';
    }

    /**
     * @return list<string>
     */
    public function getLines(): array {
        return [
            '§f',
            '§dBE SKYMINE KHOSH AMADID!',
            '§f',
            '§7HADAF SHOMA IN AST KE ORE',
            '§7HA RA MINE KONID, TOOLS VA',
            '§7ARMOR KHOD RA ERTEQA DAHID',
            '§7VA BE ANDAZEI GHAVI SHAVID',
            '§7KE BE MINE HAYE SAKHT',
            '§7NOFOZ KONID VA BA PLAYERS,',
            '§7MOBS, VA BOSS HA RO BE RO',
            '§7SHAVID.',
            '§f',
            '§aSERVER.IP §f' .
            $this->getServerAddress()
        ];
    }

    private function getServerAddress(): string
    {
        $server =
            $this->main->getServer();

        $ip = $server->getIp();
        $port = $server->getPort();

        if (
            $port > 0 &&
            $port !== 19132
        ) {
            return $ip . ':' . $port;
        }

        return $ip;
    }
}