<?php

declare(strict_types=1);

namespace AM\SkyMineZ\form;

use pocketmine\player\Player;

/**
 * Entry point of the bundled form library.
 *
 * The API mirrors jojoe77777's FormAPI: construct a form, chain the setters and
 * either pass a closure to the constructor or set it later, then hand the form
 * to a player. Everything here is bundled into SkyMineZ, so no extra plugin or
 * virion is needed on the server.
 *
 * Example:
 *
 *     $form = FormAPI::simple(static function(Player $p, mixed $data): void {
 *         if ($data === 'teleport') {
 *             $p->teleport($spawn);
 *         }
 *     });
 *     $form->setTitle('Menu')->addTextButton('Teleport');
 *     FormAPI::send($form, $player);
 */
final class FormAPI
{
    /**
     * Version of the bundled library. It tracks the upstream FormAPI release it
     * was ported from, plus the PocketMine-MP 5.44 interface additions.
     */
    public const VERSION = '2.1.1-skyminerz';

    /**
     * A form is only worth sending while the session is up; sending to a
     * disconnecting player silently does nothing but still costs a lookup.
     */
    public static function isAvailable(Player $player): bool
    {
        return $player->isConnected();
    }

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public static function simple(?callable $callable = null): SimpleForm
    {
        return new SimpleForm($callable);
    }

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public static function custom(?callable $callable = null): CustomForm
    {
        return new CustomForm($callable);
    }

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public static function modal(?callable $callable = null): ModalForm
    {
        return new ModalForm($callable);
    }

    /**
     * Sends any form to a player, silently doing nothing when the player is
     * already gone.
     */
    public static function send(
        Form $form,
        Player $player
    ): bool {
        if (!self::isAvailable($player)) {
            return false;
        }

        return $form->sendToPlayer($player);
    }

    /**
     * Yes/no dialog.
     *
     * @param callable(Player): void|null $onConfirm
     * @param callable(Player): void|null $onCompletion
     */
    public static function confirm(
        Player $player,
        string $title,
        string $content,
        ?callable $onConfirm = null,
        string $confirm = '§aYes',
        string $cancel = '§cNo',
        ?callable $onCompletion = null
    ): bool {
        if (!self::isAvailable($player)) {
            return false;
        }

        return self::modal()->ask(
            $player,
            $title,
            $content,
            $confirm,
            $cancel,
            $onConfirm,
            $onCompletion
        );
    }

    /**
     * Builds a button menu from a name => callback map.
     *
     * @param array<string, callable(Player): void> $handlers
     */
    public static function menu(
        Player $player,
        string $title,
        string $content,
        array $handlers,
        string $closeLabel = '§cClose'
    ): bool {
        if (!self::isAvailable($player)) {
            return false;
        }

        $form = self::simple(static function(
            Player $who,
            mixed $data
        ) use ($handlers): void {
            if (!is_string($data)) {
                return;
            }

            $handler = $handlers[$data] ?? null;

            if ($handler !== null) {
                $handler($who);
            }
        });

        $form->setTitle($title);
        $form->setContent($content);

        foreach ($handlers as $label => $_) {
            $form->addButton((string) $label, -1, '', (string) $label);
        }

        if ($closeLabel !== '') {
            $form->addButton($closeLabel);
        }

        return $form->sendToPlayer($player);
    }

    private function __construct()
    {
    }
}