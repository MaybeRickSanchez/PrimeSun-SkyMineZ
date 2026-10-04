<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

final class Miner
{
    public function __construct(
        private string $playerName,
        private MinerStates $states = new MinerStates()
    ) {
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getStates(): MinerStates
    {
        return $this->states;
    }

    public function setStates(
        MinerStates $states
    ): self {
        $this->states = $states;

        return $this;
    }

    public function getMined(): int
    {
        return $this->states->getMined();
    }

    public function addMined(
        int $amount = 1
    ): self {
        $this->states->addMined($amount);

        return $this;
    }

    public function getDeaths(): int
    {
        return $this->states->getDeaths();
    }

    public function addDeath(
        int $amount = 1
    ): self {
        $this->states->addDeath($amount);

        return $this;
    }

    public function getKills(): int
    {
        return $this->states->getKills();
    }

    public function addKill(
        int $amount = 1
    ): self {
        $this->states->addKill($amount);
        $this->states->addKillStreak($amount);

        return $this;
    }

    public function getKillStreak(): int
    {
        return $this->states->getKillStreak();
    }

    public function resetKillStreak(): self
    {
        $this->states->resetKillStreak();

        return $this;
    }

    public function toArray(): array
    {
        return $this->states->toArray();
    }
}