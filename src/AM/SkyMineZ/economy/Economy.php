<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

interface Economy
{
    public function loadPlayer(string $playerName): void;

    public function savePlayer(string $playerName): void;

    public function unloadPlayer(string $playerName): void;

    public function saveAll(): void;

    public function isLoaded(string $playerName): bool;

    public function get(string $playerName): int;

    /**
     * Overwrites a balance outright.
     *
     * @param string $reason forwarded to {@link EconomyChangeEvent}
     */
    public function set(
        string $playerName,
        int $amount,
        string $reason = EconomyChangeEventReason::SET
    ): void;

    /**
     * Credits a balance. Negative amounts are treated as a debit.
     *
     * @param string $reason forwarded to {@link EconomyChangeEvent}
     *
     * @return int the new balance
     */
    public function add(
        string $playerName,
        int $amount,
        string $reason = EconomyChangeEventReason::CREDITS
    ): int;

    /**
     * Debits a balance, never going below zero.
     *
     * @param string $reason forwarded to {@link EconomyChangeEvent}
     *
     * @return int the new balance
     */
    public function reduce(
        string $playerName,
        int $amount,
        string $reason = EconomyChangeEventReason::DEBITS
    ): int;

    public function has(string $playerName, int $amount): bool;

    /**
     * Stable identifier used for messages and events, e.g. "money" or "gold".
     */
    public function getType(): string;
}