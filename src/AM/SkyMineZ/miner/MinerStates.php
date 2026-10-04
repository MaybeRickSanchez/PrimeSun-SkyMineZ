<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

final class MinerStates
{
    public function __construct(
        private int $mined = 0,
        private int $deaths = 0,
        private int $kills = 0,
        private int $killStreak = 0
    ) {
    }

    public function getMined(): int
    {
        return $this->mined;
    }

    public function setMined(int $mined): self
    {
        $this->mined = max(0, $mined);

        return $this;
    }

    public function addMined(int $amount = 1): self
    {
        if ($amount > 0) {
            $this->mined += $amount;
        }

        return $this;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function setDeaths(int $deaths): self
    {
        $this->deaths = max(0, $deaths);

        return $this;
    }

    public function addDeath(int $amount = 1): self
    {
        if ($amount > 0) {
            $this->deaths += $amount;
        }

        return $this;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function setKills(int $kills): self
    {
        $this->kills = max(0, $kills);

        return $this;
    }

    public function addKill(int $amount = 1): self
    {
        if ($amount > 0) {
            $this->kills += $amount;
        }

        return $this;
    }

    public function getKillStreak(): int
    {
        return $this->killStreak;
    }

    public function setKillStreak(
        int $killStreak
    ): self {
        $this->killStreak = max(
            0,
            $killStreak
        );

        return $this;
    }

    public function addKillStreak(
        int $amount = 1
    ): self {
        if ($amount > 0) {
            $this->killStreak += $amount;
        }

        return $this;
    }

    public function resetKillStreak(): self
    {
        $this->killStreak = 0;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'mined' => $this->mined,
            'deaths' => $this->deaths,
            'kills' => $this->kills,
            'killStreak' => $this->killStreak
        ];
    }

    public static function fromArray(
        array $data
    ): self {
        return new self(
            (int) ($data['mined'] ?? 0),
            (int) ($data['deaths'] ?? 0),
            (int) ($data['kills'] ?? 0),
            (int) ($data['killStreak'] ?? 0)
        );
    }
}