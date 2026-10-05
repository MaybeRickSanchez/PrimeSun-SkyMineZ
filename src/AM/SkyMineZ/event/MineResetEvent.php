<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use AM\SkyMineZ\mine\Mine;

/**
 * Raised right before a mine is refilled. Cancelling it skips the refill, which
 * is the hook to use for maintenance windows.
 *
 * The fill itself is spread over several ticks, so listeners run before any
 * block is written.
 */
final class MineResetEvent extends CancellableSkyMineEvent
{
    public function __construct(
        private Mine $mine,
        private string $reason
    ) {
    }

    public function getMine(): Mine
    {
        return $this->mine;
    }

    /**
     * Either "manual", "scheduled" or "startup".
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}