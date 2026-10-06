<?php

declare(strict_types=1);

namespace AM\SkyMineZ\composer;

use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\ui\Ui;
use pocketmine\player\Player;

/**
 * In-game recipe management: create recipes, rename them, add inputs from the
 * held item, set the output from the held item, and delete them.
 *
 * Recipes persist straight back to config.yml (`composer.recipes`), so no
 * reload is needed and a restart keeps everything. Only items that survive a
 * /give-name round-trip are accepted — enchanted or renamed one-offs would rot
 * after a restart, so they are refused up front instead.
 */
final class ComposerAdminForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        if (!$player->hasPermission(Main::PERMISSION_ADMIN)) {
            Ui::error(
                $this->plugin,
                $player,
                Messages::get($this->plugin, Messages::COMMON_NO_PERMISSION)
            );

            return true;
        }

        $recipes = $this->plugin->getComposerManager()->getRecipes();

        $handlers = [];

        foreach ($recipes as $id => $recipe) {
            $handlers['§e' . $recipe['name']] = function(Player $who) use ($id): void {
                $this->sendRecipe($who, $id);
            };
        }

        $handlers['§aCreate recipe'] = function(Player $who): void {
            Ui::input(
                $this->plugin,
                $who,
                'New recipe',
                'Recipe id (letters, digits, _ and -)',
                function(Player $w, string $id): void {
                    Ui::input(
                        $this->plugin,
                        $w,
                        'New recipe',
                        'Display name',
                        function(Player $v, string $name) use ($id): void {
                            if (!$this->plugin->getComposerManager()->addRecipe($id, $name)) {
                                Ui::error(
                                    $this->plugin,
                                    $v,
                                    Messages::get(
                                        $this->plugin,
                                        Messages::COMPOSER_EXISTS,
                                        ['id' => $id]
                                    )
                                );

                                return;
                            }

                            Ui::success(
                                $this->plugin,
                                $v,
                                Messages::get(
                                    $this->plugin,
                                    Messages::COMPOSER_CREATED,
                                    ['id' => $id]
                                )
                            );
                            $this->sendRecipe($v, $id);
                        },
                        $id
                    );
                },
                'compact_coal'
            );
        };

        $lines = ['§7Pick a recipe to edit it, or create a new one.'];

        foreach ($recipes as $recipe) {
            $lines[] = '§f' . $recipe['name'] . ' §8(' . $recipe['id'] . ')'
                . ' §8| §7' . $this->describe($recipe);
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Composer recipes',
            implode("\n", $lines),
            $handlers
        );
    }

    private function sendRecipe(
        Player $player,
        string $id
    ): void {
        $recipe = $this->plugin->getComposerManager()->getRecipes()[$id] ?? null;

        if ($recipe === null) {
            Ui::error(
                $this->plugin,
                $player,
                Messages::get($this->plugin, Messages::COMPOSER_UNKNOWN, ['id' => $id])
            );

            return;
        }

        $handlers = [];

        $handlers['§aAdd held item as input'] = function(Player $who) use ($id): void {
            $held = $who->getInventory()->getItemInHand();

            if ($held->isNull()) {
                Ui::error(
                    $this->plugin,
                    $who,
                    Messages::get($this->plugin, Messages::COMPOSER_HOLD_ITEM)
                );

                return;
            }

            Ui::input(
                $this->plugin,
                $who,
                'Input count',
                'How many (number above 0)',
                function(Player $w, string $countRaw) use ($id): void {
                    $heldNow = $w->getInventory()->getItemInHand();
                    $count = is_numeric($countRaw) ? (int) $countRaw : 0;

                    if ($count < 1) {
                        Ui::error(
                            $this->plugin,
                            $w,
                            Messages::get($this->plugin, Messages::COMPOSER_NEED_COUNT)
                        );

                        return;
                    }

                    if (
                        $heldNow->isNull()
                        || !$this->plugin->getComposerManager()->addInput($id, $heldNow, $count)
                    ) {
                        Ui::error(
                            $this->plugin,
                            $w,
                            Messages::get(
                                $this->plugin,
                                Messages::COMPOSER_BAD_ITEM,
                                ['item' => $heldNow->isNull() ? 'air' : $heldNow->getName()]
                            )
                        );

                        return;
                    }

                    Ui::success(
                        $this->plugin,
                        $w,
                        Messages::get(
                            $this->plugin,
                            Messages::COMPOSER_INPUT_ADDED,
                            ['count' => $count, 'item' => $heldNow->getName()]
                        )
                    );
                    $this->sendRecipe($w, $id);
                },
                '9'
            );
        };

        foreach (array_values($recipe['inputs']) as $index => $input) {
            $handlers['§cRemove input #' . $index . ' ' . $input['count'] . 'x ' . $input['item']->getName()] =
                function(Player $who) use ($id, $index): void {
                    if (!$this->plugin->getComposerManager()->removeInput($id, $index)) {
                        Ui::error(
                            $this->plugin,
                            $who,
                            Messages::get($this->plugin, Messages::COMPOSER_UNKNOWN, ['id' => $id])
                        );

                        return;
                    }

                    Ui::success(
                        $this->plugin,
                        $who,
                        Messages::get($this->plugin, Messages::COMPOSER_INPUT_REMOVED)
                    );
                    $this->sendRecipe($who, $id);
                };
        }

        $handlers['§aSet held item as output'] = function(Player $who) use ($id): void {
            $held = $who->getInventory()->getItemInHand();

            if ($held->isNull()) {
                Ui::error(
                    $this->plugin,
                    $who,
                    Messages::get($this->plugin, Messages::COMPOSER_HOLD_ITEM)
                );

                return;
            }

            Ui::input(
                $this->plugin,
                $who,
                'Output count',
                'How many (number above 0)',
                function(Player $w, string $countRaw) use ($id): void {
                    $heldNow = $w->getInventory()->getItemInHand();
                    $count = is_numeric($countRaw) ? (int) $countRaw : 0;

                    if ($count < 1) {
                        Ui::error(
                            $this->plugin,
                            $w,
                            Messages::get($this->plugin, Messages::COMPOSER_NEED_COUNT)
                        );

                        return;
                    }

                    if (
                        $heldNow->isNull()
                        || !$this->plugin->getComposerManager()->setOutput($id, $heldNow, $count)
                    ) {
                        Ui::error(
                            $this->plugin,
                            $w,
                            Messages::get(
                                $this->plugin,
                                Messages::COMPOSER_BAD_ITEM,
                                ['item' => $heldNow->isNull() ? 'air' : $heldNow->getName()]
                            )
                        );

                        return;
                    }

                    Ui::success(
                        $this->plugin,
                        $w,
                        Messages::get(
                            $this->plugin,
                            Messages::COMPOSER_OUTPUT_SET,
                            ['count' => $count, 'item' => $heldNow->getName()]
                        )
                    );
                    $this->sendRecipe($w, $id);
                },
                '1'
            );
        };

        $handlers['§eRename'] = function(Player $who) use ($id, $recipe): void {
            Ui::input(
                $this->plugin,
                $who,
                'Rename recipe',
                'Display name',
                function(Player $w, string $name) use ($id): void {
                    if (!$this->plugin->getComposerManager()->renameRecipe($id, $name)) {
                        Ui::error(
                            $this->plugin,
                            $w,
                            Messages::get($this->plugin, Messages::COMPOSER_NEED_NAME)
                        );

                        return;
                    }

                    Ui::success(
                        $this->plugin,
                        $w,
                        Messages::get(
                            $this->plugin,
                            Messages::COMPOSER_RENAMED,
                            ['name' => $name]
                        )
                    );
                    $this->sendRecipe($w, $id);
                },
                $recipe['name']
            );
        };

        $handlers['§cDelete recipe'] = function(Player $who) use ($id): void {
            Ui::confirm(
                $this->plugin,
                $who,
                'Delete recipe?',
                "This removes '{$id}' for everyone.",
                function(Player $w) use ($id): void {
                    $this->plugin->getComposerManager()->removeRecipe($id);

                    Ui::success(
                        $this->plugin,
                        $w,
                        Messages::get(
                            $this->plugin,
                            Messages::COMPOSER_DELETED,
                            ['id' => $id]
                        )
                    );
                    $this->send($w);
                }
            );
        };

        $handlers['§7Back'] = function(Player $who): void {
            $this->send($who);
        };

        $out = $recipe['output'] === null
            ? '§8none yet'
            : '§a' . $recipe['output']['count'] . 'x ' . $recipe['output']['item']->getName();

        Ui::menu(
            $this->plugin,
            $player,
            $recipe['name'],
            '§7Inputs: §f' . $this->describe($recipe)
            . "\n§7Output: " . $out,
            $handlers
        );
    }

    /**
     * @param array{inputs: list<array{item: \pocketmine\item\Item, count: int}>} $recipe
     */
    private function describe(
        array $recipe
    ): string {
        $needs = [];

        foreach ($recipe['inputs'] as $input) {
            $needs[] = $input['count'] . 'x ' . $input['item']->getName();
        }

        return $needs === [] ? 'no inputs yet' : implode(' + ', $needs);
    }
}
