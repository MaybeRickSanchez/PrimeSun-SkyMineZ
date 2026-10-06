<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;

/**
 * Registers every SkyMineZ command with the server.
 *
 * Kept in one place so commands cannot drift apart: every command object
 * carries its own name, description, usage, permission and aliases, and
 * plugin.yml intentionally declares no command labels (see its note) so no
 * executor-less PluginCommand can shadow the real implementation.
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
            new HubCommand($plugin),
            new WarpCommand($plugin),
            new LabelCommand($plugin),
            new TeamCommand($plugin),
            new CrateCommand($plugin),
            new MineCommand($plugin),
            new OutpostCommand($plugin),
            new SlapperCommand($plugin),
            new LeaderboardCommand($plugin),
            new ShopCommand($plugin),
            new QuestCommand($plugin),
            new ToolCommand($plugin),
            new TradeCommand($plugin),
            new ComposerCommand($plugin)
        ];
    }
}