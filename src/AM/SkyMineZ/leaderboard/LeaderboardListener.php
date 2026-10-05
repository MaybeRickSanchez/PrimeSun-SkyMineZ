<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

/**
 * Pushes the boards to players who join after startup.
 */
final class LeaderboardListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->main->getLeaderboardManager()->spawnTo(
            $event->getPlayer()
        );
    }
}