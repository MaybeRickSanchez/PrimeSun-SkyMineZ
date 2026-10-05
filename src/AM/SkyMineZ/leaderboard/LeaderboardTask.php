<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use pocketmine\scheduler\Task;

/**
 * Refreshes every leaderboard on a fixed interval.
 *
 * Ten minutes is plenty: the underlying numbers only change when somebody plays,
 * and a faster refresh would respawn every hologram for a board that looks the
 * same. {@link Leaderboard::update()} still hashes the lines, so a refresh that
 * changes nothing sends no packets at all.
 */
final class LeaderboardTask extends Task
{
    public const INTERVAL = 20 * 60 * 10;

    public function __construct(
        private LeaderboardManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->refreshAll();
    }
}