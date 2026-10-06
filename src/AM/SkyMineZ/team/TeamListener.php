<?php

declare(strict_types=1);

namespace AM\SkyMineZ\team;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * Feeds duel outcomes into the team system.
 *
 * Dying or leaving mid-battle counts that player out of their side; when a
 * whole side is out, the other side wins immediately. Nothing here awards
 * anything directly — all scoring lives in {@link TeamManager}.
 */
final class TeamListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onDeath(
        PlayerDeathEvent $event
    ): void {
        $this->main->getTeamManager()->markOut(
            $event->getPlayer()->getName()
        );
    }

    public function onQuit(
        PlayerQuitEvent $event
    ): void {
        $this->main->getTeamManager()->markOut(
            $event->getPlayer()->getName()
        );
    }
}