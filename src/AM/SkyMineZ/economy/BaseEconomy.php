<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

use pocketmine\utils\Config;

abstract class BaseEconomy implements Economy
{
    protected int $defaultBalance = 0;

    /**
     * @var array<string, int>
     */
    protected array $balances = [];

    public function __construct(
        protected Config $database
    ) {
    }

    public function loadPlayer(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        if (isset($this->balances[$playerName])) {
            return;
        }

        $this->balances[$playerName] = (int) $this->database->get(
            $playerName,
            $this->defaultBalance
        );
    }

    public function savePlayer(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        if (!isset($this->balances[$playerName])) {
            return;
        }

        $this->database->set(
            $playerName,
            $this->balances[$playerName]
        );
    }

    public function unloadPlayer(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        if (!isset($this->balances[$playerName])) {
            return;
        }

        $this->savePlayer($playerName);

        unset($this->balances[$playerName]);
    }

    public function saveAll(): void
    {
        foreach (
            array_keys($this->balances)
            as $playerName
        ) {
            $this->savePlayer($playerName);
        }

        $this->database->save();
    }

    public function isLoaded(string $playerName): bool
    {
        return isset(
            $this->balances[
            $this->normalizeName($playerName)
            ]
        );
    }

    public function get(string $playerName): int
    {
        return $this->balances[
            $this->normalizeName($playerName)
            ] ?? 0;
    }

    public function set(
        string $playerName,
        int $amount
    ): void {
        $playerName = $this->normalizeName($playerName);

        $this->balances[$playerName] = max(
            0,
            $amount
        );
    }

    public function add(
        string $playerName,
        int $amount
    ): void {
        if ($amount <= 0) {
            return;
        }

        $playerName = $this->normalizeName($playerName);

        $this->balances[$playerName] =
            ($this->balances[$playerName] ?? 0)
            + $amount;
    }

    public function reduce(
        string $playerName,
        int $amount
    ): void {
        if ($amount <= 0) {
            return;
        }

        $playerName = $this->normalizeName($playerName);

        $current =
            $this->balances[$playerName] ?? 0;

        $this->balances[$playerName] = max(
            0,
            $current - $amount
        );
    }

    public function has(
        string $playerName,
        int $amount
    ): bool {
        if ($amount < 0) {
            return false;
        }

        return $this->get($playerName) >= $amount;
    }

    protected function normalizeName(
        string $playerName
    ): string {
        return strtolower($playerName);
    }

    /**
     * @return array<string, int>
     */
    public function getSnapshot(): array
    {
        $result = [];

        foreach ($this->database->getAll() as $playerName => $amount) {
            if (is_numeric($amount)) {
                $result[(string) $playerName] = (int) $amount;
            }
        }

        foreach ($this->balances as $playerName => $amount) {
            $result[$playerName] = $amount;
        }

        return $result;
    }

    public function getDatabase(): Config
    {
        return $this->database;
    }
}