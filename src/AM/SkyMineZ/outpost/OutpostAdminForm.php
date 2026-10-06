<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\player\Player;

/**
 * Outpost management window: inspect state, force owners, unlock, relabel and
 * delete. Progress and timers are read live, so what you see is what the next
 * tick will act on.
 */
final class OutpostAdminForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $outposts = $this->plugin->getOutpostManager()->getAll();

        if ($outposts === []) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::OUTPOST_NONE));

            return true;
        }

        $handlers = [];

        foreach ($outposts as $name => $outpost) {
            $handlers['§d' . $name . ' §8(' . ($outpost->getOwner() ?? 'free') . ')'] =
                function(Player $who) use ($name): void {
                    $this->sendOutpost($who, $name);
                };
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Outposts',
            '§7Pick an outpost to manage it.',
            $handlers
        );
    }

    private function sendOutpost(
        Player $player,
        string $name
    ): void {
        $outpost = $this->plugin->getOutpostManager()->get($name);

        if ($outpost === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::OUTPOST_GONE));

            return;
        }

        $available = $outpost->getAvailableAt() > time()
            ? NumberFormatter::duration($outpost->getAvailableAt() - time())
            : 'now';

        Ui::menu(
            $this->plugin,
            $player,
            $name,
            '§7State: §f' . $outpost->getState()
            . "\n§7Owner: §f" . ($outpost->getOwner() ?? 'none')
            . "\n§7Progress: §f" . $outpost->getProgress() . '/' . $outpost->getCaptureRequired()
            . "\n§7Capturable: §f" . $available,
            [
                '§eSet owner' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'Outpost owner',
                        'Player name, or "clear"',
                        function(Player $w, string $text) use ($name): void {
                            $outpost = $this->plugin->getOutpostManager()->get($name);

                            if ($outpost === null) {
                                return;
                            }

                            $outpost->setOwner(
                                strcasecmp($text, 'clear') === 0 ? null : $text
                            );

                            $this->plugin->getOutpostManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::OUTPOST_OWNER_UPDATED));
                            $this->sendOutpost($w, $name);
                        },
                        $outpost->getOwner() ?? ''
                    );
                },
                '§eUnlock now' => function(Player $who) use ($name): void {
                    $outpost = $this->plugin->getOutpostManager()->get($name);

                    if ($outpost === null) {
                        return;
                    }

                    $outpost->restore(
                        $outpost->getOwner(),
                        Outpost::STATE_CAPTABLE,
                        0,
                        0,
                        0
                    );

                    $this->plugin->getOutpostManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::OUTPOST_UNLOCKED));
                },
                '§eMove label here' => function(Player $who) use ($name): void {
                    $outpost = $this->plugin->getOutpostManager()->get($name);

                    if ($outpost === null) {
                        return;
                    }

                    $outpost->setLabelPosition($who->getPosition());
                    $outpost->spawn();
                    $this->plugin->getOutpostManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::COMMON_LABEL_MOVED));
                },
                '§cDelete outpost' => function(Player $who) use ($name): void {
                    Ui::confirm(
                        $this->plugin,
                        $who,
                        'Delete outpost?',
                        "This removes '{$name}' and its hologram.",
                        function(Player $w) use ($name): void {
                            $this->plugin->getOutpostManager()->remove($name);

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
}