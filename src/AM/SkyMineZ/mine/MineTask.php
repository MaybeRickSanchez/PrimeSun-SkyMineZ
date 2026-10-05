<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use pocketmine\scheduler\Task;

/**
 * Drives the automatic mine resets and the once-per-second hologram refresh.
 *
 * A single repeating task covers both: it counts down to the next due mine and
 * re-arms itself implicitly, which is cheaper than polling every mine on its own
 * timer and guarantees the mines cannot drift apart.
 *
 * The actual refill is not done here. {@link Mine::reset()} hands the writing to
 * {@link MineFillTask}, so this task only ever decides *when*.
 */
final class MineTask extends Task
{
    public const TICK_INTERVAL = 20;

    private const HOLOGRAM_INTERVAL = 20;

    private int $ticksSinceHologram = self::HOLOGRAM_INTERVAL;

    public function __construct(
        private MineManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->onTick();

        if (--$this->ticksSinceHologram <= 0) {
            $this->ticksSinceHologram = self::HOLOGRAM_INTERVAL;

            $this->manager->tickHolograms();
        }
    }
}