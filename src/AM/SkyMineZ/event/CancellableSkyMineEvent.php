<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use pocketmine\event\Cancellable;
use pocketmine\event\Event;

/**
 * Base class for SkyMineZ events a listener may veto.
 */
abstract class CancellableSkyMineEvent extends Event implements Cancellable
{
    protected bool $isCancelled = false;

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    public function setCancelled(bool $isCancelled = true): void
    {
        $this->isCancelled = $isCancelled;
    }
}