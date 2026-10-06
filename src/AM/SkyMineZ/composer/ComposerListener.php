<?php

declare(strict_types=1);

namespace AM\SkyMineZ\composer;

use AM\SkyMineZ\Main;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * Settles composer workbenches when they close.
 *
 * Close covers every exit: the player walking away, the window being replaced,
 * death, and disconnects (a forced disconnect closes the window server-side).
 * Quit additionally settles in case the close event never arrives, and both
 * paths funnel into the idempotent {@link ComposerManager::settle()}.
 */
final class ComposerListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onClose(
        InventoryCloseEvent $event
    ): void {
        $this->main->getComposerManager()->settle(
            $event->getInventory()
        );
    }

    public function onQuit(
        PlayerQuitEvent $event
    ): void {
        $manager = $this->main->getComposerManager();

        $manager->settlePlayer($event->getPlayer());
    }
}