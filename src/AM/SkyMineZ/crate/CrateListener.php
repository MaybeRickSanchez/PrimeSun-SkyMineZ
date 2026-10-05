<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\event\block\ChestPairEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;

/**
 * Wires crates to player interaction.
 *
 * Right-click opens a crate (and consumes exactly one key), sneak + right-click
 * opens the read-only reward preview, and the crate block itself cannot be
 * broken or merged with a neighbour.
 */
final class CrateListener implements Listener
{
    public function __construct(
        private CrateManager $crateManager,
        private Main $main
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

        $crate = $this->crateManager->getCrateAt(
            $event->getBlock()
                ->getPosition()
        );

        if ($crate === null) {
            return;
        }

        $player = $event->getPlayer();

        /*
         * Cancelled before anything else: the crate chest must never open as a
         * normal container, even if one of the branches below bails out.
         */
        $event->cancel();

        if ($crate->isBusy()) {
            $player->sendMessage(
                '§eThis crate is currently opening.'
            );

            return;
        }

        if ($player->isSneaking()) {
            $crate->showPreview($player);

            return;
        }

        $inventory = $player->getInventory();
        $item = $inventory->getItemInHand();

        $keyId = Key::getId($item);

        if ($keyId === null) {
            $player->sendMessage(
                '§cYou need a key to open this crate.'
            );

            return;
        }

        if (!$crate->hasKey($keyId)) {
            $player->sendMessage(
                '§cThis key cannot open this crate.'
            );

            return;
        }

        if (!$crate->open($player)) {
            return;
        }

        /*
         * Only now that the opening is guaranteed to start does the key leave
         * the player's hand.
         */
        $inventory->setItemInHand(
            $item->pop()
        );
    }

    public function onInventoryClose(
        InventoryCloseEvent $event
    ): void {
        $inventory = $event->getInventory();
        $player = $event->getPlayer();

        foreach (
            $this->crateManager->getCrates() as $crate
        ) {
            if ($crate->getInventory() !== $inventory) {
                continue;
            }

            $crate->handleClose($player);

            return;
        }
    }

    public function onBreak(
        BlockBreakEvent $event
    ): void {
        if (
            $this->crateManager->getCrateAt(
                $event->getBlock()
                    ->getPosition()
            ) === null
        ) {
            return;
        }

        $event->cancel();

        $event->getPlayer()->sendMessage(
            '§cYou cannot break a crate. Use /crate remove <name>.'
        );
    }

    /**
     * Keeps explosions from blowing a crate open. The whole explosion is
     * cancelled if it touches a crate, because Bedrock explosions have no
     * per-block way of excluding one position.
 */
    public function onExplode(
        BlockExplodeEvent $event
    ): void {
        foreach (
            $event->getAffectedBlocks() as $block
        ) {
            if (
                $this->crateManager->getCrateAt(
                    $block->getPosition()
                ) === null
            ) {
                continue;
            }

            $event->cancel();

            return;
        }
    }

    /**
     * Stops a crate chest from pairing with a normal chest, which would silently
     * merge their inventories and let players steal rewards.
     */
    public function onChestPair(
        ChestPairEvent $event
    ): void {
        if (
            $this->crateManager->getCrateAt(
                $event->getLeft()
                    ->getPosition()
            ) !== null
            || $this->crateManager->getCrateAt(
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
        $player = $event->getPlayer();

        /*
         * Chunk data has not arrived yet on the join tick, so the labels are
         * pushed a moment later, and in small batches so a server with hundreds
         * of crates does not send them all in one tick.
         */
        $this->main->getScheduler()->scheduleDelayedTask(
            new ClosureTask(
                function() use ($player): void {
                    if ($player->isConnected()) {
                        $this->crateManager->spawnTo(
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
        $player = $event->getPlayer();

        foreach (
            $this->crateManager->getCrates() as $crate
        ) {
            $crate->handleQuit($player);
        }
    }
}