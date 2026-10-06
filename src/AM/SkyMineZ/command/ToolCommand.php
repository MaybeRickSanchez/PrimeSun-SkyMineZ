<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\tools\ToolUpgradeForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /tools - progression gear for the held item: attune it, watch XP, spend
 * materials on level upgrades.
 */
final class ToolCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'tools',
            'Upgrade your held tool or armor',
            '/tools',
            ['upgrade', 'gear'],
            Main::PERMISSION_USE
        );
    }

    public function execute(
        CommandSender $sender,
        string $label,
        array $args
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TOOLS_ONLY));

            return true;
        }

        (new ToolUpgradeForm($this->plugin))->send($sender);

        return true;
    }
}