<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use AM\SkyMineZ\economy\Economy;

/**
 * Raised after a balance changed. Cancelling it reverts the balance, which makes
 * it safe for listeners to enforce their own economy rules.
 *
 * "reason" is a free-form tag such as "crate", "outpost", "command" or "admin".
 */
final class EconomyChangeEvent extends CancellableSkyMineEvent
{
    public const REASON_CREDITS = 'credits';
    public const REASON_DEBITS = 'debits';
    public const REASON_COMMAND = 'command';
    public const REASON_SET = 'set';
    public const REASON_RESET = 'reset';

    public function __construct(
        private Economy $economy,
        private string $playerName,
        private int $oldBalance,
        private int $newBalance,
        private string $reason = self::REASON_CREDITS
    ) {
    }

    public function getEconomy(): Economy
    {
        return $this->economy;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getOldBalance(): int
    {
        return $this->oldBalance;
    }

    public function getNewBalance(): int
    {
        return $this->newBalance;
    }

    public function getDelta(): int
    {
        return $this->newBalance - $this->oldBalance;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}