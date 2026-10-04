<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use pocketmine\scheduler\Task;

final class LeaderboardTask extends Task
{
    public function __construct(
        private LeaderboardManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->refreshAll();
    }
}