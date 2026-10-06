<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

/**
 * Reason tags passed to {@link \AM\SkyMineZ\event\EconomyChangeEvent} so
 * listeners can tell a crate payout from an admin command without comparing
 * numbers.
 */
final class EconomyChangeEventReason
{
    public const CREDITS = 'credits';
    public const DEBITS = 'debits';
    public const COMMAND = 'command';
    public const SET = 'set';
    public const RESET = 'reset';
    public const OUTPOST = 'outpost';

    private function __construct()
    {
    }
}