<?php

declare(strict_types=1);

namespace AM\SkyMineZ\composer;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use pocketmine\player\Player;

/**
 * The recipe picker. Each recipe shows its inputs and result up front, so
 * players know what to put in before the workbench even opens.
 */
final class ComposerMenuForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $recipes = $this->plugin->getComposerManager()->getRecipes();

        if ($recipes === []) {
            Ui::error(
                $this->plugin,
                $player,
                Messages::get($this->plugin, Messages::COMPOSER_NO_RECIPES)
            );

            return true;
        }

        $handlers = [];

        foreach ($recipes as $id => $recipe) {
            $handlers['§e' . $recipe['name']] = function(Player $who) use ($id): void {
                if (!$this->plugin->getComposerManager()->open($who, $id)) {
                    Ui::error(
                        $this->plugin,
                        $who,
                        Messages::get($this->plugin, Messages::COMPOSER_OPEN_FAIL)
                    );

                    return;
                }

                $who->sendMessage(
                    $this->plugin->getConfigManager()->getPrefix()
                    . Messages::get($this->plugin, Messages::COMPOSER_FILL_HINT)
                );
            };

        }

        $lines = ['§7Pick a recipe, fill the workbench, close to craft.'];

        foreach ($recipes as $recipe) {
            $needs = [];

            foreach ($recipe['inputs'] as $input) {
                $needs[] = $input['count'] . 'x ' . $input['item']->getName();
            }

            $lines[] = '§f' . $recipe['name'] . '§8: §7'
                . implode(' + ', $needs)
                . ' §8→ §a' . $recipe['output']['count'] . 'x '
                . $recipe['output']['item']->getName();
        }

        if ($player->hasPermission(Main::PERMISSION_ADMIN)) {
            $handlers['§6Manage recipes'] = function(Player $who): void {
                (new ComposerAdminForm($this->plugin))->send($who);
            };
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Composer',
            implode("\n", $lines),
            $handlers
        );
    }
}