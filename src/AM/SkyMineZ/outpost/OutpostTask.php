<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use pocketmine\scheduler\Task;

/**
 * Drives every outpost.
 *
 * The tick interval comes from config (`outposts.tick-interval`, 20 ticks by
 * default). One repeating task covers the whole set instead of one task per
 * outpost, so a server with fifty outposts still only pays for a single
 * scheduler entry.
 */
final class OutpostTask extends Task
{
    public function __construct(
        private OutpostManager $manager
    ) {
    }

    public function onRun(): void
    {
        $this->manager->tickAll();
    }

}