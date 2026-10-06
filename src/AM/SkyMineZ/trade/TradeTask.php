<?php

declare(strict_types=1);

namespace AM\SkyMineZ\trade;

use pocketmine\scheduler\Task;

/**
 * Reaps timed-out trade requests and abandoned sessions. Runs every 30
 * seconds: timeouts are measured in minutes, so anything faster would only
 * burn ticks checking an usually-empty map.
 */
final class TradeTask extends Task
{
    public const INTERVAL = 600;

    public function __construct(
        private TradeManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->tick();
    }
}