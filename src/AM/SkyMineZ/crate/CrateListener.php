<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;

/**
 * Wires crates to player interaction.
 *
 * Right-click runs the crate-opening flow in a virtual window (never the
 * shulker itself), sneak + right-click previews the rewards, and the crate
 * block itself cannot be broken or blown up. Keys are consumed exactly once,
 * and only after the opening provably started.
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
                Messages::get($this->main, Messages::CRATE_BUSY)
            );

            return;
        }

        if ($player->isSneaking()) {
            $crate->showPreview($player);

            return;
        }

        $config = $this->main->getConfigManager();
        $keyId = null;

        if ($config->getBool('crates.require-key', true)) {
            $inventory = $player->getInventory();
            $item = $inventory->getItemInHand();

            $keyId = Key::getId($item);

            if ($keyId === null) {
                $player->sendMessage(
                    Messages::get($this->main, Messages::CRATE_NO_KEY)
                );

                return;
            }

            if (!$crate->hasKey($keyId)) {
                $player->sendMessage(
                    Messages::get($this->main, Messages::CRATE_WRONG_KEY)
                );

                return;
            }
        }

        if (!$crate->open($player)) {
            return;
        }

        /*
         * Only now that the opening is guaranteed to start does the key leave
         * the player's hand — and only when the server is configured to eat
         * keys. Either flag off means free openings.
         */
        if (
            $keyId !== null
            && $config->getBool('crates.consume-key', true)
        ) {
            $inventory = $player->getInventory();
            $item = $inventory->getItemInHand();

            if (Key::getId($item) === $keyId) {
                // pop() returns the removed single item and shrinks $item in
                // place: the hand must receive the shrunken remainder, not
                // the popped key (which would keep 1 key forever, or delete
                // a whole stack down to 1).
                $item->pop();
                $inventory->setItemInHand($item);
            }
        }
    }

    public function onInventoryClose(
        InventoryCloseEvent $event
    ): void {
        $inventory = $event->getInventory();
        $player = $event->getPlayer();

        foreach (
            $this->crateManager->getCrates() as $crate
        ) {
            if (!$crate->isMyWindow($inventory)) {
                continue;
            }

            $crate->handleClose($player, $inventory);

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
            Messages::get($this->main, Messages::CRATE_NO_BREAK)
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