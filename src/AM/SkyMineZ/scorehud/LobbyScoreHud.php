<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\player\Player;

/**
 * The sidebar players see once they leave the spawn area.
 *
 * Every line comes from `scoreboard.lobby.lines` in config.yml, with
 * {placeholders} replaced. A missing placeholder is left as-is so a typo is
 * visible in-game instead of silently vanishing.
 */
final class LobbyScoreHud
{
    public function __construct(
        private Main $main
    ) {
    }

    public function getTitle(): string
    {
        return $this->main
            ->getConfigManager()
            ->getString(
                'scoreboard.lobby.title',
                '§d§lSkyMine'
            );
    }

    /**
     * @return list<string>
     */
    public function getLines(
        Player $player
    ): array {
        $miner = $this->main->getMinerManager()->getOrLoad(
            $player->getName()
        );

        $placeholders = [
            'name' => $player->getName(),
            'prefix_name' => $player->getName(),
            'gold' => NumberFormatter::short(
                $this->main->getGoldEconomy()->get(
                    $player->getName()
                )
            ),
            'money' => NumberFormatter::short(
                $this->main->getMoneyEconomy()->get(
                    $player->getName()
                )
            ),
            'mined' => NumberFormatter::short(
                $miner->getMined()
            ),
            'deaths' => NumberFormatter::short(
                $miner->getDeaths()
            ),
            'kills' => NumberFormatter::short(
                $miner->getKills()
            ),
            'kill_streak' => (string) $miner->getKillStreak(),
            'pvp' => $this->main->getPvpManager()->getState(
                $player->getName()
            ) ? 'ON' : 'OFF',
            'server_address' => ServerAddress::of($this->main)
        ];

        $result = [];

        foreach (
            $this->main->getConfigManager()->getStringList(
                'scoreboard.lobby.lines'
            ) as $line
        ) {
            $result[] = str_replace(
                array_map(
                    static fn(string $key): string => '{' . $key . '}',
                    array_keys($placeholders)
                ),
                array_values($placeholders),
                $line
            );
        }

        return $result;
    }
}