<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\event\OutpostCaptureEvent;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * A capturable zone.
 *
 * Progress rises while at least one player stands inside the box. When two
 * players are inside, the one whose position is furthest from the centre wins,
 * which stops two players standing in the same spot from ping-ponging ownership.
 *
 * Reaching the required progress raises {@link OutpostCaptureEvent}, then the
 * outpost switches to COOLDOWN so the previous owner has a window of exclusivity.
 */
final class Outpost
{
    public const STATE_CAPTABLE = 'CAPTABLE';
    public const STATE_COOLDOWN = 'COOLDOWN';

    private OutpostBox $box;

    private OutpostInfo $info;

    private string $state = self::STATE_CAPTABLE;

    private ?string $owner = null;

    private ?string $capturer = null;

    private int $progress = 0;

    private int $availableAt = 0;

    private int $lastGoldAt = 0;

    /**
     * @param int $cooldownDuration seconds the outpost stays locked after a capture
     * @param int $goldInterval     seconds between two gold payouts
     * @param int $goldChance       percent chance a payout happens when due
     * @param int $goldReward       gold granted on a successful payout
     * @param int $captureRequired  progress needed to take the outpost
     */
    public function __construct(
        string $name,
        Vector3 $pos1,
        Vector3 $pos2,
        World $world,
        private int $captureRequired = 100,
        private int $cooldownDuration = 1800,
        private int $goldInterval = 600,
        private int $goldChance = 50,
        private int $goldReward = 1
    ) {
        $this->box = new OutpostBox(
            $pos1,
            $pos2,
            $world
        );

        $this->info = new OutpostInfo(
            $name,
            $this->centerOf($pos1, $pos2, $world)
        );
    }

    private function centerOf(
        Vector3 $pos1,
        Vector3 $pos2,
        World $world
    ): Position {
        return Position::fromObject(
            new Vector3(
                ($pos1->x + $pos2->x) / 2,
                min(
                    $pos1->y,
                    $pos2->y
                ),
                ($pos1->z + $pos2->z) / 2
            ),
            $world
        );
    }

    public function getName(): string
    {
        return $this->info->getName();
    }

    public function getBox(): OutpostBox
    {
        return $this->box;
    }

    public function getInfo(): OutpostInfo
    {
        return $this->info;
    }

    public function getWorld(): World
    {
        return $this->box->getWorld();
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isCapturable(): bool
    {
        return $this->state === self::STATE_CAPTABLE;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function getCapturer(): ?string
    {
        return $this->capturer;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getAvailableAt(): int
    {
        return $this->availableAt;
    }

    public function getCaptureRequired(): int
    {
        return $this->captureRequired;
    }

    public function setOwner(
        ?string $owner
    ): self {
        $this->owner = $owner;

        return $this;
    }

    public function spawn(): void
    {
        $this->info->spawn();
    }

    public function deSpawn(): void
    {
        $this->info->deSpawn();
    }

    public function isIn(
        Vector3 $position
    ): bool {
        return $this->box->isIn($position);
    }

    /**
     * Restores the runtime state after the outpost was loaded from disk.
     */
    public function restore(
        ?string $owner,
        string $state,
        int $progress,
        int $availableAt,
        int $lastGoldAt
    ): self {
        $this->owner = $owner;
        $this->state = $state === self::STATE_COOLDOWN
            ? self::STATE_COOLDOWN
            : self::STATE_CAPTABLE;
        $this->progress = max(
            0,
            min(
                $this->captureRequired - 1,
                $progress
            )
        );
        $this->availableAt = $availableAt;
        $this->lastGoldAt = $lastGoldAt;

        if (
            $this->state === self::STATE_COOLDOWN
            && $this->availableAt <= time()
        ) {
            $this->state = self::STATE_CAPTABLE;
            $this->availableAt = 0;
        }

        return $this;
    }

    /**
     * One tick of outpost logic: cooldown expiry, capture progress and hologram
     * refresh.
     *
     * @return bool whether the state changed in a way the manager should announce
     */
    public function tick(
        int $now,
        int $captureMin,
        int $captureMax
    ): bool {
        $changed = false;

        if (
            $this->state === self::STATE_COOLDOWN
            && $now >= $this->availableAt
        ) {
            $this->state = self::STATE_CAPTABLE;
            $this->availableAt = 0;
            $this->progress = 0;

            $changed = true;
        }

        if ($this->state === self::STATE_CAPTABLE) {
            $changed = $this->tickCapture(
                $now,
                $captureMin,
                $captureMax
            ) || $changed;
        }

        $this->info->update(
            $this->state,
            $this->owner,
            $this->capturer,
            $this->progress,
            $this->captureRequired,
            $this->availableAt,
            $now
        );

        return $changed;
    }

    /**
     * @return bool whether the outpost was captured this tick
     */
    private function tickCapture(
        int $now,
        int $captureMin,
        int $captureMax
    ): bool {
        $capturer = $this->pickCapturer();

        if ($capturer === null) {
            $this->capturer = null;
            $this->progress = 0;

            return false;
        }

        $this->capturer = $capturer;

        $this->progress += mt_rand(
            max(1, $captureMin),
            max(
                max(1, $captureMin),
                $captureMax
            )
        );

        if ($this->progress < $this->captureRequired) {
            return false;
        }

        $this->progress = $this->captureRequired;

        return $this->capture(
            $capturer,
            $now
        );
    }

    /**
     * Picks who is capturing: the occupant furthest from the centre of the box,
     * so two players in the same corner do not fight over it every tick.
     */
    private function pickCapturer(): ?string
    {
        $best = null;
        $bestDistance = -1.0;

        $center = $this->box->getCenter();

        foreach (
            $this->box->getWorld()->getPlayers() as $player
        ) {
            $position = $player->getPosition();

            if (!$this->box->isIn($position)) {
                continue;
            }

            $distance = $position->distanceSquared(
                $center
            );

            if ($distance <= $bestDistance) {
                continue;
            }

            $bestDistance = $distance;
            $best = $player->getName();
        }

        return $best;
    }

    /**
     * True when the owner is due a gold payout. Calling this method consumes the
     * timer, so only call it once per tick.
     */
    public function isGoldDue(
        int $now
    ): bool {
        if (
            $this->owner === null
            || $this->goldInterval <= 0
        ) {
            return false;
        }

        if ($this->lastGoldAt === 0) {
            $this->lastGoldAt = $now;

            return false;
        }

        if (
            $now - $this->lastGoldAt < $this->goldInterval
        ) {
            return false;
        }

        $this->lastGoldAt = $now;

        return mt_rand(
            1,
            100
        ) <= max(
            0,
            min(
                100,
                $this->goldChance
            )
        );
    }

    public function getGoldReward(): int
    {
        return $this->goldReward;
    }

    /**
     * Hands the outpost to $playerName, unless a listener cancels it.
     */
    public function capture(
        string $playerName,
        int $now
    ): bool {
        $previousOwner = $this->owner;

        if (!OutpostCaptureEvent::hasHandlers()) {
            return $this->applyCapture(
                $playerName,
                $previousOwner,
                $now
            );
        }

        $event = new OutpostCaptureEvent(
            $this,
            $playerName,
            $previousOwner
        );

        $event->call();

        if ($event->isCancelled()) {
            return false;
        }

        return $this->applyCapture(
            $playerName,
            $previousOwner,
            $now
        );
    }

    private function applyCapture(
        string $playerName,
        ?string $previousOwner,
        int $now
    ): bool {
        $this->owner = $playerName;
        $this->capturer = null;
        $this->progress = 0;
        $this->state = self::STATE_COOLDOWN;
        $this->availableAt = $now + $this->cooldownDuration;
        $this->lastGoldAt = $now;

        return true;
    }

    /**
     * @return array{
     *     world: string,
     *     pos1: array{float, float, float},
     *     pos2: array{float, float, float},
     *     owner: string|null,
     *     state: string,
     *     progress: int,
     *     availableAt: int,
     *     lastGoldAt: int
     * }
     */
    public function toArray(): array
    {
        return [
            'world' => $this->getWorld()->getFolderName(),
            'pos1' => [
                $this->box->getPos1()->x,
                $this->box->getPos1()->y,
                $this->box->getPos1()->z
            ],
            'pos2' => [
                $this->box->getPos2()->x,
                $this->box->getPos2()->y,
                $this->box->getPos2()->z
            ],
            'owner' => $this->owner,
            'state' => $this->state,
            'progress' => $this->progress,
            'availableAt' => $this->availableAt,
            'lastGoldAt' => $this->lastGoldAt
        ];
    }
}