<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\trade\TradeManager;
use AM\SkyMineZ\ui\Ui;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /trade - chest-style player trading.
 *
 * `/trade <player>` sends a request, `/trade accept` starts the shared
 * window, and a second `/trade accept` (one per side) completes it. Closing
 * the window, dying, disconnecting or idling too long returns everything, so
 * items can never get stuck.
 */
final class TradeCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'trade',
            'Trade items safely with another player',
            '/trade <player|accept|deny|cancel>',
            ['trades'],
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

        $sub = strtolower($args[0] ?? '');

        if ($sub === '') {
            return $this->handleStatus($sender);
        }

        return match ($sub) {
            'accept' => $this->handleAccept($sender),
            'deny' => $this->handleDeny($sender),
            'cancel' => $this->handleCancel($sender),
            default => $this->handleRequest($sender, $args[0])
        };
    }

    private function manager(): TradeManager
    {
        return $this->plugin->getTradeManager();
    }

    /**
     * @param list<string> $args
     */
    private function handleRequest(
        CommandSender $sender,
        ?string $target
    ): bool {
        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_ONLY));

            return true;
        }

        if ($target === null) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_USAGE));

            return true;
        }

        $this->manager()->request($sender, $target)
            ? $this->success($sender, Messages::get($this->plugin, Messages::TRADE_SENT, ['player' => (string) $target]))
            : $this->error($sender, Messages::get($this->plugin, Messages::TRADE_REQUEST_FAIL));

        return true;
    }

    private function handleAccept(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_ONLY));

            return true;
        }

        $manager = $this->manager();

        // A pending invitation comes first; otherwise this confirms a session.
        if ($manager->acceptRequest($sender)) {
            return true;
        }

        $id = $manager->sessionIdOf($sender->getName());

        if ($id === null) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_NOTHING));

            return true;
        }

        $manager->confirm($sender);
        $this->success($sender, Messages::get($this->plugin, Messages::TRADE_CONFIRMED_WAIT));

        return true;
    }

    private function handleDeny(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_ONLY));

            return true;
        }

        $this->manager()->denyRequest($sender)
            ? $this->success($sender, Messages::get($this->plugin, Messages::TRADE_DECLINED))
            : $this->error($sender, Messages::get($this->plugin, Messages::TRADE_NOTHING_DECLINE));

        return true;
    }

    private function handleCancel(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_ONLY));

            return true;
        }

        $id = $this->manager()->sessionIdOf($sender->getName());

        if ($id === null) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_NO_SESSION));

            return true;
        }

        $this->manager()->cancel($id, Messages::get($this->plugin, Messages::TRADE_CANCELLED));

        return true;
    }

    private function handleStatus(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::TRADE_ONLY));

            return true;
        }

        $manager = $this->manager();
        $id = $manager->sessionIdOf($sender->getName());

        if ($id === null) {
            Ui::menu(
                $this->plugin,
                $sender,
                'Trade',
                "§7No open trade.\n§7/trade <player> §8- §7ask someone to trade",
                []
            );

            return true;
        }

        $side = $manager->sideOf($id, $sender->getName());

        Ui::menu(
            $this->plugin,
            $sender,
            'Trade',
            "§7You are side §f" . strtoupper((string) $side) . "§7.\n"
            . "§7Top half is side A, bottom half is side B.\n"
            . "§7Confirmed: §f"
            . ($manager->isConfirmed($id, 'a') ? '§aA yes' : '§cA no')
            . ' §8| §f'
            . ($manager->isConfirmed($id, 'b') ? '§aB yes' : '§cB no'),
            [
                '§aConfirm trade' => function(Player $who): void {
                    $this->manager()->confirm($who);
                },
                '§cCancel trade' => function(Player $who): void {
                    $mid = $this->manager()->sessionIdOf($who->getName());

                    if ($mid !== null) {
                        $this->manager()->cancel($mid, Messages::get($this->plugin, Messages::TRADE_CANCELLED));
                    }
                }
            ]
        );

        return true;
    }
}