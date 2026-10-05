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

    /**
     * @return array{mined: int, deaths: int, kills: int, killStreak: int}
     */
    public function toArray(): array
    {
        return [
            'mined' => $this->mined,
            'deaths' => $this->deaths,
            'kills' => $this->kills,
            'killStreak' => $this->killStreak
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data
    ): self {
        return new self(
            self::readInt($data, 'mined'),
            self::readInt($data, 'deaths'),
            self::readInt($data, 'kills'),
            self::readInt($data, 'killStreak')
        );
    }

    /**
     * Reads one counter out of a decoded JSON record. Anything that is not a
     * number (a hand-edited file, a schema change) reads as zero rather than
     * poisoning the stats with a cast of null.
     *
     * @param array<string, mixed> $data
     */
    private static function readInt(
        array $data,
        string $key
    ): int {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}