<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use pocketmine\scheduler\Task;

/**
 * Runs {@link LagMaker::tick()} once per tick.
 *
 * A dedicated task instead of a closure keeps the scheduler entry cancellable, so
 * /skymine reload can stop and restart it without leaving a stray closure
 * running for the lifetime of the server.
 */
final class LagMakerTask extends Task
{
    public function __construct(
        private LagMaker $lagMaker
    ) {
    }

    public function onRun(): void
    {
        $this->lagMaker->tick();
    }
}
