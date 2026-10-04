<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\ChestPairEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;

final class CrateListener implements Listener
{
    public function __construct(
        private CrateManager $crateManager
    ) {
    }

    public function onInteract(
        PlayerInteractEvent $event
    ): void {
        if (
            $event->getAction() !==
            PlayerInteractEvent::RIGHT_CLICK_BLOCK
        ) {
            return;
        }

        $crate =
            $this->crateManager->getCrateAt(
                $event->getBlock()->getPosition()
            );

        if ($crate === null) {
            return;
        }

        $event->cancel();

        $player =
            $event->getPlayer();

        if ($crate->isBusy()) {
            $player->sendMessage(
                '§eThis crate is currently opening.'
            );

            return;
        }

        if ($player->isSneaking()) {
            $crate->showPreview(
                $player
            );

            return;
        }

        $item =
            $player
                ->getInventory()
                ->getItemInHand();

        $keyId =
            Key::getId($item);

        if ($keyId === null) {
            $player->sendMessage(
                '§cYou need a key to open this crate.'
            );

            return;
        }

        if (
            !$crate->hasKey(
                $keyId
            )
        ) {
            $player->sendMessage(
                '§cThis key cannot open this crate.'
            );

            return;
        }

        if (
            !$crate->open(
                $player
            )
        ) {
            return;
        }

        $item->pop();

        $player
            ->getInventory()
            ->setItemInHand(
                $item
            );
    }

    public function onInventoryClose(
        InventoryCloseEvent $event
    ): void {
        $inventory =
            $event->getInventory();

        foreach (
            $this->crateManager->getCrates()
            as $crate
        ) {
            if (
                $crate->getInventory()
                !== $inventory
            ) {
                continue;
            }

            $crate->handleClose(
                $event->getPlayer()
            );

            return;
        }
    }

    public function onBreak(
        BlockBreakEvent $event
    ): void {
        $crate =
            $this->crateManager->getCrateAt(
                $event->getBlock()->getPosition()
            );

        if ($crate === null) {
            return;
        }

        $event->cancel();

        $event->getPlayer()->sendMessage(
            '§cYou cannot break a crate.'
        );
    }

    public function onChestPair(
        ChestPairEvent $event
    ): void {
        if (
            $this->crateManager->getCrateAt(
                $event->getLeft()
                    ->getPosition()
            ) !== null ||
            $this->crateManager->getCrateAt(
                $event->getRight()
                    ->getPosition()
            ) !== null
        ) {
            $event->cancel();
        }
    }

    public function onJoin(
        PlayerJoinEvent $event
    ): void {
        $player =
            $event->getPlayer();

        Main::getInstance()
            ->getScheduler()
            ->scheduleDelayedTask(
                new ClosureTask(
                    function () use (
                        $player
                    ): void {
                        if (
                            !$player->isConnected()
                        ) {
                            return;
                        }

                        foreach (
                            $this->crateManager
                                ->getCrates()
                            as $crate
                        ) {
                            $crate->spawnText(
                                $player
                            );
                        }
                    }
                ),
                1
            );
    }

    public function onQuit(
        PlayerQuitEvent $event
    ): void {
        foreach (
            $this->crateManager->getCrates()
            as $crate
        ) {
            $crate->handleQuit(
                $event->getPlayer()
            );
        }
    }
}