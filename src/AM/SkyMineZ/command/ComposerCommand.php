<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\composer\ComposerAdminForm;
use AM\SkyMineZ\composer\ComposerMenuForm;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /composer - opens the material composer after picking a recipe.
 */
final class ComposerCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'composer',
            'Combine materials into new items',
            '/composer',
            ['compose', 'craft'],
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
            $this->error(
                $sender,
                Messages::get($this->plugin, Messages::COMPOSER_ONLY)
            );

            return true;
        }

        if (
            strtolower($args[0] ?? '') === 'manage'
            && $sender->hasPermission(Main::PERMISSION_ADMIN)
        ) {
            (new ComposerAdminForm($this->plugin))->send($sender);

            return true;
        }

        (new ComposerMenuForm($this->plugin))->send($sender);

        return true;
    }
}