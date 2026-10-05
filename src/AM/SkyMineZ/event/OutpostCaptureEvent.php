<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use AM\SkyMineZ\outpost\Outpost;

/**
 * Raised when an outpost reached full capture progress. Cancelling it keeps
 * the current owner (or leaves the outpost unowned) and does not start the
 * cooldown.
 */
final class OutpostCaptureEvent extends CancellableSkyMineEvent
{
    public function __construct(
        private Outpost $outpost,
        private string $newOwner,
        private ?string $previousOwner
    ) {
    }

    public function getOutpost(): Outpost
    {
        return $this->outpost;
    }

    public function getNewOwner(): string
    {
        return $this->newOwner;
    }

    public function getPreviousOwner(): ?string
    {
        return $this->previousOwner;
    }
}