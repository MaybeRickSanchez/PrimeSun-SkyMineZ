<?php

declare(strict_types=1);

namespace AM\SkyMineZ\tools;

use AM\SkyMineZ\event\MinerBlockMinedEvent;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;

/**
 * Progression gear behavior: XP on breaking, no voluntary drops.
 *
 * XP hooks into {@link MinerBlockMinedEvent} so cancelled or excluded breaks
 * (protection, worthless blocks) never grant anything. Drops are refused
 * outright — gear leaves the inventory only through death or admin commands,
 * never by accident.
 */
final class ToolListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onMined(
        MinerBlockMinedEvent $event
    ): void {
        if ($event->isCancelled()) {
            return;
        }

        $manager = $this->main->getToolManager();

        if ($manager->xpPerBlock() <= 0) {
            return;
        }

        $leveled = $manager->addXp(
            $event->getPlayer(),
            $manager->xpPerBlock() * $event->getAmount()
        );

        if ($leveled !== null) {
            $event->getPlayer()->sendMessage(
                $this->main->getConfigManager()->getPrefix()
                . Messages::get(
                    $this->main,
                    Messages::TOOLS_READY,
                    ['level' => $leveled]
                )
            );
        }
    }

    public function onDrop(
        PlayerDropItemEvent $event
    ): void {
        if (ToolManager::isGear($event->getItem())) {
            $event->cancel();

            $event->getPlayer()->sendMessage(
                $this->main->getConfigManager()->getPrefix()
                . Messages::get($this->main, Messages::TOOLS_NO_DROP)
            );
        }
    }
}