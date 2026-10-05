<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;

/**
 * The sidebar players see while they stand in the spawn area.
 *
 * Like the lobby board it is fully configurable, see
 * `scoreboard.welcome.lines`.
 */
final class MiddleLobbyScoreHud
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
                'scoreboard.welcome.title',
                '§d§lSkyMine'
            );
    }

    /**
     * @return list<string>
     */
    public function getLines(): array
    {
        $config = $this->main->getConfigManager();

        $placeholders = [
            'server_address' => ServerAddress::of(
                $this->main
            )
        ];

        $search = [];
        $replace = [];

        foreach (
            $placeholders as $key => $value
        ) {
            $search[] = '{' . $key . '}';
            $replace[] = $value;
        }

        $result = [];

        foreach (
            $config->getStringList(
                'scoreboard.welcome.lines'
            ) as $line
        ) {
            $result[] = str_replace(
                $search,
                $replace,
                $line
            );
        }

        return $result;
    }
}