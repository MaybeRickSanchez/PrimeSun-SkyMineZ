<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use AM\SkyMineZ\crate\Crate;
use AM\SkyMineZ\crate\Reward;
use pocketmine\item\Item;

/**
 * Raised once a crate has picked a winner but before the animation starts and
 * before the key is consumed. Cancelling it aborts the opening, which is the
 * hook to use for region protection, cooldowns or extra costs.
 */
final class CrateOpenEvent extends CancellableSkyMineEvent
{
    public function __construct(
        private Crate $crate,
        private string $playerName,
        private Reward $reward
    ) {
    }

    public function getCrate(): Crate
    {
        return $this->crate;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getReward(): Reward
    {
        return $this->reward;
    }

    /**
     * A copy of the reward item, safe to modify or hand out.
     */
    public function getItem(): Item
    {
        return $this->reward->getItem();
    }

    /**
     * Replaces the reward that will be rolled out.
     */
    public function setReward(Reward $reward): void
    {
        $this->reward = $reward;
    }
}