<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

/**
 * The floating label above an outpost: capture progress, state, owner and the
 * countdown until the outpost can be taken again.
 */
final class OutpostInfo
{
    private const LINE_PROGRESS = 0;
    private const LINE_STATE = 1;
    private const LINE_OWNER = 2;
    private const LINE_CAPTURER = 3;
    private const LINE_TIMER = 4;
    private const LINE_HINT = 5;

    private const LINE_COUNT = 6;

    private ?Position $position;

    private ?MultiLineTextParticle $particle = null;

    public function __construct(
        private string $name,
        ?Position $position = null
    ) {
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

    public function setPosition(
        ?Position $position
    ): self {
        $this->position = $position;

        if ($this->particle === null) {
            return $this;
        }

        if ($position === null) {
            $this->particle->deSpawn();
            $this->particle = null;

            return $this;
        }

        $this->particle->setPosition(
            $this->computeBasePosition($position),
            $position->getWorld()
        );

        return $this;
    }

    public function spawn(): void
    {
        $particle = $this->ensureParticle();

        if ($particle === null) {
            return;
        }

        if (!$particle->isSpawned()) {
            $particle->spawn();
        }
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

    public function getParticle(): ?MultiLineTextParticle
    {
        return $this->particle;
    }

    public function update(
        string $state,
        ?string $owner,
        ?string $capturer,
        int $progress,
        int $captureRequired,
        int $availableAt,
        int $now
    ): void {
        $particle = $this->ensureParticle();

        if ($particle === null) {
            return;
        }

        $particle->setLine(
            self::LINE_PROGRESS,
            "§e" . $this->name . " §7- §f"
            . NumberFormatter::bar(
                $captureRequired > 0
                    ? $progress / $captureRequired
                    : 0.0,
                10,
                '§a',
                '§8'
            )
            . " §f"
            . $progress . '/'
            . $captureRequired
        );

        $particle->setLine(
            self::LINE_STATE,
            $state === Outpost::STATE_COOLDOWN
                ? "§cLOCKED"
                : "§aCAPTURABLE"
        );

        $particle->setLine(
            self::LINE_OWNER,
            "§7Owner: §f" . ($owner ?? 'None')
        );

        $particle->setLine(
            self::LINE_CAPTURER,
            $capturer === null
                ? ""
                : "§7Capturer: §e" . $capturer
        );

        if (
            $state === Outpost::STATE_COOLDOWN
            && $availableAt > $now
        ) {
            $particle->setLine(
                self::LINE_TIMER,
                "§7Available in §e"
                . NumberFormatter::duration($availableAt - $now)
            );
        } else {
            $particle->setLine(
                self::LINE_TIMER,
                ""
            );
        }

        $particle->setLine(
            self::LINE_HINT,
            $state === Outpost::STATE_COOLDOWN
                ? ""
                : "§7Stand here to capture"
        );
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
                array_fill(
                    0,
                    self::LINE_COUNT,
                    ''
                )
            );
        }

        return $this->particle;
    }

    /**
     * MultiLineTextParticle lays its lines downwards from the base position.
     * The label is anchored at its floor level, so the base has to be raised by
     * one full stack or the hologram would sink into the ground.
     */
    private function computeBasePosition(
        Position $anchor
    ): Vector3 {
        return new Vector3(
            $anchor->x,
            $anchor->y + (
                (self::LINE_COUNT - 1) * MultiLineTextParticle::LINE_SPACING
            ),
            $anchor->z
        );
    }
}