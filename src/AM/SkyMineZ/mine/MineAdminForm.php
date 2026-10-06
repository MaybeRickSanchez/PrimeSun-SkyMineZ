<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\command\BlockParser;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\player\Player;

/**
 * Mine management window: inspect, refill, retime, relabel, edit the block
 * list and delete. Numeric input is validated before anything is written, and
 * deletion confirms first.
 */
final class MineAdminForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $mines = $this->plugin->getMineManager()->getAll();

        if ($mines === []) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::MINE_NONE));

            return true;
        }

        $handlers = [];

        foreach ($mines as $name => $mine) {
            $handlers['§d' . $name] = function(Player $who) use ($name): void {
                $this->sendMine($who, $name);
            };
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Mines',
            '§7Pick a mine to manage it.',
            $handlers
        );
    }

    private function sendMine(
        Player $player,
        string $name
    ): void {
        $mine = $this->plugin->getMineManager()->get($name);

        if ($mine === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::MINE_UNKNOWN, ['name' => $name]));

            return;
        }

        $box = $mine->getMineBox();

        Ui::menu(
            $this->plugin,
            $player,
            $name,
            '§7Volume: §f' . NumberFormatter::short($box->getVolume())
            . ' §8| §7Interval: §f' . ($mine->getResetInterval() > 0
                ? NumberFormatter::duration($mine->getResetInterval())
                : 'manual')
            . "\n§7Blocks: §f" . count($mine->getBlocks())
            . ' §8| §7State: §f' . ($mine->isFilling() ? 'refilling' : 'idle'),
            [
                '§aRefill now' => function(Player $who) use ($name): void {
                    if (!$this->plugin->getMineManager()->reset($name, 'manual')) {
                        Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_REFILL_BUSY));

                        return;
                    }

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_REFILLING));
                },
                '§eSet interval' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'Reset interval',
                        'Seconds between auto resets (0 = manual only)',
                        function(Player $w, string $text) use ($name): void {
                            $mine = $this->plugin->getMineManager()->get($name);

                            if ($mine === null || !is_numeric($text) || (int) $text < 0) {
                                Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::MINE_BAD_INTERVAL));

                                return;
                            }

                            $mine->setResetInterval((int) $text);
                            $this->plugin->getMineManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::MINE_INTERVAL_SET));
                            $this->sendMine($w, $name);
                        },
                        (string) $mine->getResetInterval()
                    );
                },
                '§eMove label here' => function(Player $who) use ($name): void {
                    $mine = $this->plugin->getMineManager()->get($name);

                    if ($mine === null) {
                        return;
                    }

                    $mine->getInfo()->setPosition($who->getPosition());
                    $mine->getInfo()->spawn();
                    $this->plugin->getMineManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::COMMON_LABEL_MOVED));
                },
                '§eBlocks' => function(Player $who) use ($name): void {
                    $this->sendBlocks($who, $name);
                },
                '§cDelete mine' => function(Player $who) use ($name): void {
                    Ui::confirm(
                        $this->plugin,
                        $who,
                        'Delete mine?',
                        "This removes '{$name}' and its hologram. The blocks stay.",
                        function(Player $w) use ($name): void {
                            $this->plugin->getMineManager()->remove($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::COMMON_DELETED, ['name' => $name]));
                        }
                    );
                },
                '§7Back' => function(Player $who): void {
                    $this->send($who);
                }
            ]
        );
    }

    private function sendBlocks(
        Player $player,
        string $name
    ): void {
        $mine = $this->plugin->getMineManager()->get($name);

        if ($mine === null) {
            return;
        }

        $handlers = [];
        $lines = ['§7Percentages scale automatically when they do not add up to 100.'];

        foreach ($mine->getBlocks() as $index => $entry) {
            $lines[] = '§f#' . $index . ' §f' . $entry->getName()
                . ' §8| §e' . $entry->getPercent() . '%';

            $handlers['§cRemove #' . $index] = function(Player $who) use ($name, $index): void {
                $mine = $this->plugin->getMineManager()->get($name);

                if ($mine === null) {
                    return;
                }

                $mine->removeBlock($index);
                $this->plugin->getMineManager()->save($name);

                Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_BLOCK_REMOVED));
                $this->sendBlocks($who, $name);
            };
        }

        if ($handlers === []) {
            $lines[] = '§7No blocks yet. Hold one and use the button below.';
        }

        $handlers['§aAdd held block (60%)'] = function(Player $who) use ($name): void {
            $mine = $this->plugin->getMineManager()->get($name);

            if ($mine === null) {
                return;
            }

            $held = $who->getInventory()->getItemInHand();

            if ($held->isNull()) {
                Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_HOLD_BLOCK));

                return;
            }

            // Display names lie (custom names, renames); parse first, then
            // fall back to the held block mapping without ever throwing out
            // of the form.
            $block = BlockParser::parse($held->getName());

            if ($block === null) {
                try {
                    $block = $held->getBlock();
                } catch (\Throwable) {
                    $block = null;
                }
            }

            if ($block === null || $block->isAir()) {
                Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_HOLD_SOLID));

                return;
            }

            try {
                $mine->addBlock(new MineBlock(60, $block));
            } catch (\Throwable $exception) {
                Ui::error($this->plugin, $who, $exception->getMessage());

                return;
            }

            $this->plugin->getMineManager()->save($name);

            Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::MINE_BLOCK_ADDED));
            $this->sendBlocks($who, $name);
        };

        $handlers['§7Back'] = function(Player $who) use ($name): void {
            $this->sendMine($who, $name);
        };

        Ui::menu(
            $this->plugin,
            $player,
            $name . ' blocks',
            implode("\n", $lines),
            $handlers
        );
    }
}