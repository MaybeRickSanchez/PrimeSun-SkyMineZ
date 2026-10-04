<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

class OutpostInfo
{
    private const LINE_COUNT = 6;

    private string $name;

    private ?Position $position;

    private ?MultiLineTextParticle $particle = null;

    public function __construct(string $name, ?Position $position = null)
    {
        $this->name = $name;
        $this->position = $position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): void
    {
        $this->position = $position;

        if ($this->particle === null) {
            return;
        }

        if ($position !== null) {
            $this->particle->setPosition($this->computeBasePosition($position));
        } else {
            $this->particle->deSpawn();
            $this->particle = null;
        }
    }

    public function spawn(): void
    {
        $particle = $this->ensureParticle();

        if ($particle === null) {
            return;
        }

        $particle->spawn();
    }

    public function deSpawn(): void
    {
        $this->particle?->deSpawn();
    }

    public function update(
        string $state,
        ?string $owner,
        ?string $capturer,
        int $progress,
        int $availableAt,
        int $now
    ): void {
        $particle = $this->ensureParticle();

        if ($particle === null) {
            return;
        }

        $particle->setLine(0, $progress . "%");
        $particle->setLine(1, "STATE: " . $state);
        $particle->setLine(2, "OWNER: " . ($owner ?? "None"));
        $particle->setLine(
            3,
            $capturer !== null ? "CAPTURER: " . $capturer : ""
        );

        if ($state === Outpost::STATE_COOLDOWN && $availableAt > $now) {
            $remaining = $availableAt - $now;
            $minutes = intdiv($remaining, 60);
            $seconds = $remaining % 60;
            $particle->setLine(
                4,
                sprintf("AVAILABLE ON: %dm %ds", $minutes, $seconds)
            );
        } else {
            $particle->setLine(4, "");
        }

        $particle->setLine(5, "Stand Here To Capture Outpost");
    }

    private function ensureParticle(): ?MultiLineTextParticle
    {
        if ($this->position === null) {
            return null;
        }

        if ($this->particle === null) {
            $this->particle = new MultiLineTextParticle(
                $this->computeBasePosition($this->position),
                $this->position->getWorld(),
                array_fill(0, self::LINE_COUNT, "")
            );
        }

        return $this->particle;
    }

    /**
     * MultiLineTextParticle lays lines downward from basePosition.
     * To keep the bottom-most line on $anchor.y and have the rest stack
     * upward, we raise the base by (LINE_COUNT - 1) * LINE_SPACING.
     */
    private function computeBasePosition(Position $anchor): Vector3
    {
        return new Vector3(
            $anchor->x,
            $anchor->y
            + (self::LINE_COUNT - 1) * MultiLineTextParticle::LINE_SPACING,
            $anchor->z
        );
    }
}