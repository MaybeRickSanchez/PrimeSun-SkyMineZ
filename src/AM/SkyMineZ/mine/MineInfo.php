<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\world\Position;

/**
 * The floating label above a mine: its name, the countdown to the next reset and
 * a progress bar.
 *
 * Every setter is a no-op until a position has been set, because there is
 * nowhere to show the text.
 */
final class MineInfo
{
    public const LINE_NAME = 0;
    public const LINE_TIMER = 1;
    public const LINE_BAR = 2;
    public const LINE_STATUS = 3;

    private ?Position $position;

    private string $mineName;

    private int $nextResetAt = 0;

    private int $resetInterval = 0;

    private bool $filling = false;

    private string $lastReason = '';

    private ?MultiLineTextParticle $particle = null;

    public function __construct(
        string $mineName,
        ?Position $position = null
    ) {
        $this->mineName = $mineName;
        $this->position = $position;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(
        ?Position $position
    ): self {
        $this->position = $position;

        if ($position === null) {
            $this->particle?->deSpawn();
            $this->particle = null;

            return $this;
        }

        if ($this->particle !== null) {
            $this->particle->setPosition($position);
        }

        return $this;
    }

    public function getMineName(): string
    {
        return $this->mineName;
    }

    public function setMineName(
        string $mineName
    ): self {
        $this->mineName = $mineName;

        $this->updateName();

        return $this;
    }

    public function getNextResetAt(): int
    {
        return $this->nextResetAt;
    }

    public function setNextResetAt(
        int $timestamp
    ): self {
        $this->nextResetAt = $timestamp;

        return $this;
    }

    public function getResetInterval(): int
    {
        return $this->resetInterval;
    }

    public function setResetInterval(
        int $seconds
    ): self {
        $this->resetInterval = max(
            0,
            $seconds
        );

        return $this;
    }

    public function isFilling(): bool
    {
        return $this->filling;
    }

    public function setFilling(
        bool $filling
    ): self {
        $this->filling = $filling;

        $this->updateTime();

        return $this;
    }

    public function getLastReason(): string
    {
        return $this->lastReason;
    }

    public function setLastReason(
        string $reason
    ): self {
        $this->lastReason = $reason;

        return $this;
    }

    public function spawn(): void
    {
        $particle = $this->ensureParticle();

        if ($particle === null) {
            return;
        }

        $this->updateName();
        $this->updateTime();

        if (!$particle->isSpawned()) {
            $particle->spawn();
        }
    }

    private function ensureParticle(): ?MultiLineTextParticle
    {
        if ($this->position === null) {
            return null;
        }

        if ($this->particle === null) {
            $this->particle = new MultiLineTextParticle(
                $this->position,
                $this->position->getWorld()
            );
        }

        return $this->particle;
    }

    public function deSpawn(): void
    {
        $this->particle?->deSpawn();
    }

    public function isSpawned(): bool
    {
        return $this->particle !== null
            && $this->particle->isSpawned();
    }

    /**
     * The live hologram, or null while no position is set. Used to push the
     * text to a player who joined after the mine was spawned.
     */
    public function getParticle(): ?MultiLineTextParticle
    {
        return $this->particle;
    }

    public function updateName(): void
    {
        if ($this->particle === null) {
            return;
        }

        $this->particle->setLine(
            self::LINE_NAME,
            "§d" . $this->mineName
        );
    }

    /**
     * Refreshes the countdown line. Cheap enough to call once per second, and
     * a no-op while the text is unchanged.
     */
    public function updateTime(): void
    {
        if ($this->particle === null) {
            return;
        }

        if ($this->filling) {
            $this->particle->setLine(
                self::LINE_TIMER,
                "§eRefilling..."
            );

            $this->particle->setLine(
                self::LINE_BAR,
                ""
            );

            return;
        }

        if (
            $this->resetInterval <= 0
            || $this->nextResetAt <= 0
        ) {
            $this->particle->setLine(
                self::LINE_TIMER,
                "§7Manual reset only"
            );

            $this->particle->setLine(
                self::LINE_BAR,
                ""
            );

            return;
        }

        $remaining = max(
            0,
            $this->nextResetAt - time()
        );

        $elapsed = $this->resetInterval - $remaining;

        $this->particle->setLine(
            self::LINE_TIMER,
            "§fResets in §e"
            . NumberFormatter::duration($remaining)
        );

        $this->particle->setLine(
            self::LINE_BAR,
            NumberFormatter::bar(
                $elapsed / $this->resetInterval,
                10,
                '§a',
                '§8'
            )
        );
    }
}