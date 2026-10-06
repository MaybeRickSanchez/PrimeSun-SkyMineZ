<?php

declare(strict_types=1);

namespace AM\SkyMineZ\tools;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use AM\SkyMineZ\useless\Items;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\item\Durable;
use pocketmine\player\Player;

/**
 * The gear window for the held item.
 *
 * Three states, each explained on screen: not gear yet (attune it), gear with
 * an upgrade available (requirements shown have/need style), and gear with
 * nothing to do yet (progress towards the next level). The confirm step
 * re-validates everything against the live inventory — swapping items
 * mid-window upgrades nothing.
 */
final class ToolUpgradeForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $held = $player->getInventory()->getItemInHand();

        if ($held->isNull()) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::TOOLS_HOLD));

            return true;
        }

        if (!ToolManager::isGear($held)) {
            $this->sendAttune($player);

            return true;
        }

        $this->sendUpgrade($player);

        return true;
    }

    private function sendAttune(
        Player $player
    ): void {
        $held = $player->getInventory()->getItemInHand();

        if (!$held instanceof Durable || $held->isNull()) {
            Ui::error(
                $this->plugin,
                $player,
                Messages::get($this->plugin, Messages::TOOLS_NEED_DURABLE)
            );

            return;
        }

        Ui::menu(
            $this->plugin,
            $player,
            'Progression gear',
            "§7This " . $held->getName() . " is ordinary.\n"
            . "§7Attune it to make it §eunbreakable§7, §eundroppable §7and able to earn XP and levels.\n"
            . "§7Attuning is free and starts at level 1.",
            [
                '§aAttune this ' . $held->getName() => function(Player $who): void {
                    $item = $who->getInventory()->getItemInHand();

                    if (!$item instanceof Durable || $item->isNull()) {
                        Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::TOOLS_HOLD));

                        return;
                    }

                    $gear = $this->plugin->getToolManager()->attune($item);

                    if ($gear === null) {
                        Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::TOOLS_NO_GEAR));

                        return;
                    }

                    $who->getInventory()->setItemInHand($gear);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::TOOLS_ATTUNED));

                    $this->send($who);
                }
            ]
        );
    }

    private function sendUpgrade(
        Player $player
    ): void {
        $manager = $this->plugin->getToolManager();
        $held = $player->getInventory()->getItemInHand();

        $level = ToolManager::getLevel($held);
        $xp = ToolManager::getXp($held);
        $max = $manager->maxLevel();

        if ($level >= $max) {
            Ui::menu(
                $this->plugin,
                $player,
                'Gear level ' . $level,
                "§7This piece is maxed out.\n§7Level: §e{$level} §8| §7XP: §f" . NumberFormatter::short($xp),
                []
            );

            return;
        }

        $next = $level + 1;
        $needXp = $manager->xpForLevel($next);
        $package = $manager->upgradeFor($next);

        if ($package['enchants'] === [] && $package['materials'] === []) {
            Ui::menu(
                $this->plugin,
                $player,
                'Gear level ' . $level,
                "§7Level {$next} has no upgrade configured yet.\n§7XP: §f" . NumberFormatter::short($xp)
                . ' §8/ §f' . NumberFormatter::short($needXp),
                []
            );

            return;
        }

        $lines = [
            '§7Level: §e' . $level . ' §8→ §e' . $next,
            '§7XP: §f' . NumberFormatter::short($xp) . ' §8/ §f' . NumberFormatter::short($needXp)
            . ($xp >= $needXp ? ' §a(enough)' : ' §c(keep mining)')
        ];

        if ($package['enchants'] !== []) {
            $lines[] = '§7Gain:';

            foreach ($package['enchants'] as $enchant) {
                $lines[] = '§f- ' . $enchant['id'] . ' ' . $enchant['level'];
            }
        }

        if ($package['materials'] !== []) {
            $lines[] = '§7Costs:';
            $inventory = $player->getInventory();

            foreach ($package['materials'] as $material) {
                $have = Items::countOf($inventory, $material['item']);

                $lines[] = '§f- ' . $material['count'] . 'x '
                    . $material['item']->getName()
                    . ' §8(§f' . $have . '§8)'
                    . ($have >= $material['count'] ? ' §a(ok)' : ' §c(missing)');
            }
        }

        $handlers = [];

        $ready = $xp >= $needXp;

        if ($ready) {
            $handlers['§aUpgrade to level ' . $next] = function(Player $who): void {
                if (!$this->plugin->getToolManager()->upgrade($who)) {
                    Ui::error(
                        $this->plugin,
                        $who,
                        Messages::get($this->plugin, Messages::TOOLS_UPGRADE_FAIL)
                    );

                    return;
                }

                Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::TOOLS_UPGRADED));

                $this->send($who);
            };
        }

        Ui::menu(
            $this->plugin,
            $player,
            'Gear level ' . $level,
            implode("\n", $lines),
            $handlers
        );
    }
}