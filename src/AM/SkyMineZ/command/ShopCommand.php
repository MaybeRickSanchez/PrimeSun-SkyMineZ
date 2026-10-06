<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\shop\ShopMenuForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /shop - opens the category shop. Stock and prices come from config.yml, so
 * this command is only a door; there is nothing to manage through it.
 */
final class ShopCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'shop',
            'Open the shop',
            '/shop',
            ['store', 'market'],
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
            $this->error($sender, Messages::get($this->plugin, Messages::SHOP_ONLY));

            return true;
        }

        (new ShopMenuForm($this->plugin))->send($sender);

        return true;
    }
}