<?php

declare(strict_types=1);

namespace AM\SkyMineZ\label;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

/**
 * Pushes labels to players who join after startup.
 */
final class LabelListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->main->getLabelManager()->spawnTo(
            $event->getPlayer()
        );
    }
}