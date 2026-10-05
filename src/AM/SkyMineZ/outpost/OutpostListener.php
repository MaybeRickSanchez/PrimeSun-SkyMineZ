<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

/**
 * Outpost capture needs no jump event any more: the tick itself scans the box
 * for occupants, so a player only has to stand still. This listener only pushes
 * the holograms to joining players.
 */
final class OutpostListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->main->getOutpostManager()->spawnTo(
            $event->getPlayer()
        );
    }
}