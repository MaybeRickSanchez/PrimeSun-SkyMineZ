<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use pocketmine\world\Position;

class MineInfo
{
    private ?Position $position;

    private string $mineName;

    private int $nextResetAt = 0;

    private ?MultiLineTextParticle $particle = null;

    public function __construct(string $mineName, ?Position $position = null)
    {
        $this->mineName = $mineName;
        $this->position = $position;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): void
    {
        $this->position = $position;

        if ($this->particle !== null) {
            if ($position !== null) {
                $this->particle->setPosition($position);
            } else {
                $this->particle->deSpawn();
                $this->particle = null;
            }
        }
    }

    public function getMineName(): string
    {
        return $this->mineName;
    }

    public function setMineName(string $mineName): void
    {
        $this->mineName = $mineName;
        $this->updateName();
    }

    public function setNextResetAt(int $timestamp): void
    {
        $this->nextResetAt = $timestamp;
    }

    public function getNextResetAt(): int
    {
        return $this->nextResetAt;
    }

    public function spawn(): void
    {
        // Nothing to show if no position has been set yet
        if ($this->position === null) {
            return;
        }

        if ($this->particle === null) {
            $this->particle = new MultiLineTextParticle(
                $this->position,
                $this->position->getWorld(),
                []
            );
        }

        $this->updateName();
        $this->updateTime();
        $this->particle->spawn();
    }

    public function deSpawn(): void
    {
        $this->particle?->deSpawn();
    }

    public function updateName(): void
    {
        if ($this->position === null || $this->particle === null) {
            return;
        }

        $this->particle->setLine(0, "Mine: " . $this->mineName);
    }

    public function updateTime(): void
    {
        if ($this->position === null || $this->particle === null) {
            return;
        }

        $remaining = max(0, $this->nextResetAt - time());
        $minutes = intdiv($remaining, 60);
        $seconds = $remaining % 60;

        $this->particle->setLine(
            1,
            sprintf("Next reset: %02d:%02d", $minutes, $seconds)
        );
    }
}