<?php

declare(strict_types=1);

namespace AM\SkyMineZ\team;

use pocketmine\scheduler\Task;

/**
 * Slow maintenance for teams: expires old invitations and challenges, and
 * ends battles that ran past their time limit. Runs every 100 ticks — these
 * lifetimes are measured in minutes, so tick precision would only burn CPU.
 */
final class TeamTask extends Task
{
    public const INTERVAL = 100;

    public function __construct(
        private TeamManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->tick();
    }
}