<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;

/**
 * Registers every SkyMineZ command with the server.
 *
 * Kept in one place so plugin.yml and the code cannot drift apart: the names
 * below are exactly the labels plugin.yml declares.
 */
final class CommandRegistry
{
    private function __construct()
    {
    }

    public static function registerAll(
        Main $plugin
    ): void {
        $commandMap = $plugin->getServer()
            ->getCommandMap();

        foreach (
            self::commands(
                $plugin
            ) as $command
        ) {
            $commandMap->register(
                Main::PLUGIN_NAME,
                $command
            );
        }
    }

    /**
     * @return list<BaseCommand>
     */
    public static function commands(
        Main $plugin
    ): array {
        return [
            new SkyMineCommand($plugin),
            new CrateCommand($plugin),
            new MineCommand($plugin),
            new OutpostCommand($plugin),
            new SlapperCommand($plugin),
            new LeaderboardCommand($plugin)
        ];
    }
}