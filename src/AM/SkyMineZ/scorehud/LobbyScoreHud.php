<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\miner\Miner;
use pocketmine\player\Player;

class LobbyScoreHud
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
    public function getLines(
        Player $player
    ): array {
        $miner =
            $this->main
                ->getMinerManager()
                ->getOrLoad(
                    $player->getName()
                );

        $money = $this->main->getMoneyEconomy()->get($player->getName());

        $gold =$this->main->getGoldEconomy()->get($player->getName());

        $mined =
            $miner->getMined();

        $deaths =
            $miner->getDeaths();

        $killStreak =
            $miner->getKillStreak();

        return [
            '§f',
            '§d' . $player->getName(),
            '§f',
            '§eGOLD: §f' .
            self::formatNumber($gold),
            '§eMONEY: §f' .
            self::formatNumber($money),
            '§eMINED: §f' .
            self::formatNumber($mined),
            '§eDEATHS: §f' .
            self::formatNumber($deaths),
            '§eKILL STREAK: §f' .
            $killStreak . ' §7(0)',
            '§eSKILL LEVEL: §f0',
            '§eISLAND LEVEL: §f0',
            '§eSHARD: §f0',
            '§eLEVEL: §f0 §7(0.00/50.00)',
            '§f',
            '§aSERVER.IP §f' .
            $this->getServerAddress()
        ];
    }

    private function getServerAddress(): string
    {
        $server =
            $this->main->getServer();

        $ip =
            $server->getIp();

        $port =
            $server->getPort();

        if (
            $port > 0 &&
            $port !== 19132
        ) {
            return $ip . ':' . $port;
        }

        return $ip;
    }

    public static function formatNumber(
        int|float $number
    ): string {
        $negative = $number < 0;
        $number = abs((float) $number);

        if ($number < 1000) {
            $result = number_format(
                $number,
                0,
                '.',
                ''
            );

            return $negative
                ? '-' . $result
                : $result;
        }

        $suffixes = [
            'K',
            'M',
            'B',
            'T',
            'Qa',
            'Qi',
            'Sx',
            'Sp',
            'Oc',
            'No',
            'Dc',
            'Ud',
            'Dd',
            'Td',
            'Qad',
            'Qid',
            'Sxd',
            'Spd',
            'Ocd',
            'Nod'
        ];

        $index = 0;

        while (
            $number >= 1000 &&
            $index < count($suffixes) - 1
        ) {
            $number /= 1000;
            ++$index;
        }

        if (
            $index === count($suffixes) - 1 &&
            $number >= 1000
        ) {
            $result = sprintf(
                '%.2e',
                $number
            );

            return $negative
                ? '-' . $result
                : $result;
        }

        $result = rtrim(
            rtrim(
                number_format(
                    $number,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );

        return ($negative ? '-' : '') .
            $result .
            $suffixes[$index];
    }
}