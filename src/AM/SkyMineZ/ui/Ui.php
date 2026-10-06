<?php

declare(strict_types=1);

namespace AM\SkyMineZ\ui;

use AM\SkyMineZ\form\CustomForm;
use AM\SkyMineZ\form\FormAPI;
use AM\SkyMineZ\Main;
use pocketmine\player\Player;

/**
 * The one shared form toolkit every management and player UI is built from.
 *
 * Instead of ten different form architectures, every window in the plugin is
 * one of three shapes: a button list (`menu()`), a yes/no question
 * (`confirm()`), or a small input sheet (`input()`). All three validate,
 * report errors in chat with the plugin prefix, and hand structured values
 * (never raw indexes) to the callback.
 */
final class Ui
{
    private function __construct()
    {
    }

    /**
     * Button menu. Keys are the stable values the handler receives, so
     * inserting a button never shifts meaning.
     *
     * @param array<string, callable(Player): void> $handlers label => action
     */
    public static function menu(
        Main $plugin,
        Player $player,
        string $title,
        string $content,
        array $handlers
    ): bool {
        return FormAPI::menu(
            $player,
            $plugin->getConfigManager()->getPrefix() . $title,
            $content,
            $handlers
        );
    }

    /**
     * Yes/no question. Only an explicit "yes" runs the callback; closing the
     * window or pressing "no" does nothing.
     *
     * @param callable(Player): void $onConfirm
     */
    public static function confirm(
        Main $plugin,
        Player $player,
        string $title,
        string $content,
        callable $onConfirm,
        string $confirmLabel = '§aConfirm',
        string $cancelLabel = '§cCancel'
    ): bool {
        return FormAPI::confirm(
            $player,
            $plugin->getConfigManager()->getPrefix() . $title,
            $content,
            static function(Player $who) use ($onConfirm): void {
                if ($who->isConnected()) {
                    $onConfirm($who);
                }
            },
            $confirmLabel,
            $cancelLabel
        );
    }

    /**
     * Single text input sheet.
     *
     * @param callable(Player, string): void $onSubmit receives the trimmed text;
     *                                  empty submissions are rejected with an
     *                                  error message instead of calling back
     */
    public static function input(
        Main $plugin,
        Player $player,
        string $title,
        string $fieldLabel,
        callable $onSubmit,
        string $placeholder = '',
        string $default = ''
    ): bool {
        $form = new CustomForm(
            static function(
                Player $who,
                mixed $data
            ) use ($onSubmit, $plugin): void {
                if (!is_array($data)) {
                    return;
                }

                $raw = $data[0] ?? '';

                if (!is_scalar($raw)) {
                    return;
                }

                $text = trim((string) $raw);

                if ($text === '') {
                    $who->sendMessage(
                        $plugin->getConfigManager()->getPrefix()
                        . \AM\SkyMineZ\config\Messages::get($plugin, \AM\SkyMineZ\config\Messages::COMMON_MUST_TYPE)
                    );

                    return;
                }

                if ($who->isConnected()) {
                    $onSubmit($who, $text);
                }
            }
        );

        $form->setTitle($plugin->getConfigManager()->getPrefix() . $title);
        $form->addInput($fieldLabel, $placeholder, $default !== '' ? $default : null, 0);

        return FormAPI::send($form, $player);
    }

    /**
     * Sends a red error line with the plugin prefix.
     */
    public static function error(
        Main $plugin,
        Player $player,
        string $message
    ): void {
        $player->sendMessage(
            $plugin->getConfigManager()->getPrefix() . '§c' . $message
        );
    }

    /**
     * Sends a green success line with the plugin prefix.
     */
    public static function success(
        Main $plugin,
        Player $player,
        string $message
    ): void {
        $player->sendMessage(
            $plugin->getConfigManager()->getPrefix() . '§a' . $message
        );
    }
}