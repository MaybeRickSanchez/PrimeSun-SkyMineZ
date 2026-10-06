<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use pocketmine\scheduler\Task;

/**
 * Drives the automatic mine resets and the hologram countdown refresh.
 *
 * A single repeating task covers both: it checks for due refills and refreshes
 * every countdown once per second. Refreshing every second (not every 20) is
 * what keeps the label and the actual reset timer from drifting apart; the
 * refresh itself is cheap because holograms only re-send changed lines.
 *
 * The actual refill is not done here. {@link Mine::reset()} hands the writing to
 * {@link MineFillTask}, so this task only ever decides *when*.
 */
final class MineTask extends Task
{
    public const TICK_INTERVAL = 20;

    public function __construct(
        private MineManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->tick();
    }
}