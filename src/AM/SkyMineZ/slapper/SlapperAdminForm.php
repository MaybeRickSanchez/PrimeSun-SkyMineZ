<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use AM\SkyMineZ\command\BlockParser;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use pocketmine\player\Player;

/**
 * Slapper management window: create with your skin, edit messages and
 * commands, attach blocks, move and delete.
 *
 * Creation needs nothing prepared: the NPC spawns at your feet wearing your
 * skin, and this same window opens right away so behaviour can be added
 * immediately.
 */
final class SlapperAdminForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $slappers = $this->plugin->getSlapperManager()->getSlappers();

        $handlers = [
            '§aCreate slapper here' => function(Player $who): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'New slapper',
                    'Slapper name',
                    function(Player $w, string $name): void {
                        try {
                            $this->plugin->getSlapperManager()->addSlapper(
                                $name,
                                $w->getPosition(),
                                $w
                            );
                        } catch (\Throwable $exception) {
                            Ui::error($this->plugin, $w, $exception->getMessage());

                            return;
                        }

                        $this->plugin->getSlapperManager()->save($name);

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::SLAPPER_CREATED, ['name' => $name]));
                        $this->sendSlapper($w, $name);
                    },
                    'guide'
                );
            }
        ];

        foreach ($slappers as $name => $slapper) {
            $handlers['§d' . $name] = function(Player $who) use ($name): void {
                $this->sendSlapper($who, $name);
            };
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Slappers',
            '§7NPCs that talk and run commands when clicked.',
            $handlers
        );
    }

    private function sendSlapper(
        Player $player,
        string $name
    ): void {
        $slapper = $this->plugin->getSlapperManager()->getSlapper($name);

        if ($slapper === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::SLAPPER_GONE));

            return;
        }

        Ui::menu(
            $this->plugin,
            $player,
            $name,
            '§7Messages: §f' . count($slapper->getMessages())
            . ' §8| §7Commands: §f' . count($slapper->getCommands())
            . ' §8| §7Blocks: §f' . count(
                $this->plugin->getSlapperManager()->getBlocksForSlapper($name)
            ),
            [
                '§aAdd message' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'New message',
                        'Text ({player} becomes the clicker)',
                        function(Player $w, string $text) use ($name): void {
                            $slapper = $this->plugin->getSlapperManager()->getSlapper($name);

                            if ($slapper === null) {
                                return;
                            }

                            $slapper->addMessage($text);
                            $this->plugin->getSlapperManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::SLAPPER_MSG_ADDED));
                            $this->sendSlapper($w, $name);
                        },
                        'Hello {player}!'
                    );
                },
                '§aAdd command' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'New command',
                        'Command without slash ({player} works)',
                        function(Player $w, string $text) use ($name): void {
                            $slapper = $this->plugin->getSlapperManager()->getSlapper($name);

                            if ($slapper === null) {
                                return;
                            }

                            $slapper->addCommand($text);
                            $this->plugin->getSlapperManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::SLAPPER_CMD_ADDED));
                            $this->sendSlapper($w, $name);
                        },
                        'say hi {player}'
                    );
                },
                '§eMessages' => function(Player $who) use ($name): void {
                    $this->sendList(
                        $who,
                        $name,
                        'messages',
                        $this->plugin->getSlapperManager()->getSlapper($name)?->getMessages() ?? []
                    );
                },
                '§eCommands' => function(Player $who) use ($name): void {
                    $this->sendList(
                        $who,
                        $name,
                        'commands',
                        $this->plugin->getSlapperManager()->getSlapper($name)?->getCommands() ?? []
                    );
                },
                '§eMove here' => function(Player $who) use ($name): void {
                    if (!$this->plugin->getSlapperManager()->move($name, $who->getPosition())) {
                        Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::SLAPPER_GONE));

                        return;
                    }

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::SLAPPER_MOVED));
                },
                '§eUse my skin' => function(Player $who) use ($name): void {
                    $slapper = $this->plugin->getSlapperManager()->getSlapper($name);

                    if ($slapper === null) {
                        return;
                    }

                    $slapper->setSkin($who->getSkin());
                    $this->plugin->getSlapperManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::SLAPPER_SKIN));
                },
                '§eAttach block here' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'Attach block',
                        'Block name + label, e.g. "stone info"',
                        function(Player $w, string $text) use ($name): void {
                            $parts = preg_split('/\s+/', trim($text), 2);
                            $blockName = $parts[0] ?? '';
                            $label = $parts[1] ?? $name;

                            $block = BlockParser::parse($blockName);

                            if ($block === null) {
                                Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::COMMON_UNKNOWN_BLOCK, ['block' => $blockName]));

                                return;
                            }

                            $position = \AM\SkyMineZ\command\TargetResolver::blockOrBelow($w)->getPosition();

                            // blockOrBelow can yield air (looking at sky falls
                            // back to below, but that is air when jumping).
                            // Refuse air so blocks are never placed mid-air.
                            $world = $position->getWorld();
                            $targetBlock = $world->getBlock($position);

                            if ($targetBlock->isAir()) {
                                $below = $position->down(1);
                                $belowBlock = $world->getBlock($below);

                                if ($belowBlock->isAir()) {
                                    Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::SLAPPER_SOLID_ONLY));

                                    return;
                                }

                                $position = $belowBlock->getPosition();
                            }

                            try {
                                $this->plugin->getSlapperManager()->addBlock(
                                    $label,
                                    $position,
                                    $block,
                                    $name
                                );
                            } catch (\Throwable $exception) {
                                Ui::error($this->plugin, $w, $exception->getMessage());

                                return;
                            }

                            $this->plugin->getSlapperManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::SLAPPER_ATTACHED, ['label' => $label]));
                        },
                        'stone info'
                    );
                },
                '§cDelete slapper' => function(Player $who) use ($name): void {
                    Ui::confirm(
                        $this->plugin,
                        $who,
                        'Delete slapper?',
                        "This removes '{$name}' and its blocks.",
                        function(Player $w) use ($name): void {
                            $this->plugin->getSlapperManager()->removeSlapper($name);
                            $this->plugin->getSlapperManager()->saveAll();

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

    /**
     * @param list<string> $entries
     */
    private function sendList(
        Player $player,
        string $name,
        string $kind,
        array $entries
    ): void {
        $handlers = [];

        foreach ($entries as $index => $entry) {
            $handlers['§c#' . $index . ' §8| §7' . $entry] =
                function(Player $who) use ($name, $kind, $index): void {
                    $slapper = $this->plugin->getSlapperManager()->getSlapper($name);

                    if ($slapper === null) {
                        return;
                    }

                    if ($kind === 'messages') {
                        $slapper->removeMessage($index);
                    } else {
                        $slapper->removeCommand($index);
                    }

                    $this->plugin->getSlapperManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::SLAPPER_REMOVED));
                    $this->sendSlapper($who, $name);
                };
        }

        if ($handlers === []) {
            $handlers['§7(nothing here yet)'] = function(Player $who) use ($name): void {
                $this->sendSlapper($who, $name);
            };
        }

        $handlers['§7Back'] = function(Player $who) use ($name): void {
            $this->sendSlapper($who, $name);
        };

        Ui::menu(
            $this->plugin,
            $player,
            $name . ' ' . $kind,
            '§7Tap an entry to remove it.',
            $handlers
        );
    }
}