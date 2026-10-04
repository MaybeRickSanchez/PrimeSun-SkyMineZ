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

    public function set(string $playerName, int $amount): void;

    public function add(string $playerName, int $amount): void;

    public function reduce(string $playerName, int $amount): void;

    public function has(string $playerName, int $amount): bool;
}