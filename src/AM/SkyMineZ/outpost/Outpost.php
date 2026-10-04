<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

class Outpost
{
    public const STATE_CAPTABLE = "CAPTABLE";
    public const STATE_COOLDOWN = "COOLDOWN";

    public const COOLDOWN_DURATION = 30 * 60;
    public const GOLD_INTERVAL     = 10 * 60;
    public const GOLD_CHANCE       = 50;
    public const CAPTURE_MIN       = 1;
    public const CAPTURE_MAX       = 3;
    public const GOLD_REWARD       = 1;

    private OutpostBox $box;

    private OutpostInfo $info;

    private string $state = self::STATE_CAPTABLE;

    private ?string $owner = null;

    private ?string $capturer = null;

    private int $progress = 0;

    private int $availableAt = 0;

    private int $lastGoldAt = 0;

    /** @var array<string, true> */
    private array $candidates = [];

    public function __construct(
        string $name,
        Vector3 $pos1,
        Vector3 $pos2,
        World $world
    ) {
        $this->box = new OutpostBox($pos1, $pos2, $world);

        $center = new Vector3(
            ($pos1->x + $pos2->x) / 2,
            min($pos1->y, $pos2->y),
            ($pos1->z + $pos2->z) / 2
        );

        $this->info = new OutpostInfo(
            $name,
            Position::fromObject($center, $world)
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

    public function getState(): string
    {
        return $this->state;
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

    public function spawn(): void
    {
        $this->info->spawn();
    }

    public function deSpawn(): void
    {
        $this->info->deSpawn();
    }

    public function addCandidate(string $playerName): void
    {
        $this->candidates[$playerName] = true;
    }

    public function removeCandidate(string $playerName): void
    {
        unset($this->candidates[$playerName]);
    }

    public function tick(int $now): void
    {
        if ($this->state === self::STATE_COOLDOWN) {
            if ($now >= $this->availableAt) {
                $this->state = self::STATE_CAPTABLE;
            }
        }

        if ($this->state === self::STATE_CAPTABLE) {
            $this->tickCapture($now);
        }

        $this->info->update(
            $this->state,
            $this->owner,
            $this->capturer,
            $this->progress,
            $this->availableAt,
            $now
        );
    }

    private function tickCapture(int $now): void
    {
        $server = Server::getInstance();
        $active = null;

        foreach ($this->candidates as $name => $_) {
            $player = $server->getPlayerExact($name);

            if ($player === null || !$this->box->isIn($player->getPosition())) {
                unset($this->candidates[$name]);
                continue;
            }

            $active = $name;
        }

        if ($active === null) {
            $this->capturer = null;
            $this->progress = 0;
            return;
        }

        $this->capturer = $active;
        $this->progress += mt_rand(self::CAPTURE_MIN, self::CAPTURE_MAX);

        if ($this->progress >= 100) {
            $this->progress = 100;
            $this->capture($active, $now);
        }
    }

    public function isGoldDue(int $now): bool
    {
        if ($this->owner === null) {
            return false;
        }

        if ($this->lastGoldAt === 0) {
            $this->lastGoldAt = $now;
            return false;
        }

        if ($now - $this->lastGoldAt < self::GOLD_INTERVAL) {
            return false;
        }

        $this->lastGoldAt = $now;

        return mt_rand(1, 100) <= self::GOLD_CHANCE;
    }

    private function capture(string $playerName, int $now): void
    {
        $this->owner = $playerName;
        $this->capturer = null;
        $this->progress = 0;
        $this->state = self::STATE_COOLDOWN;
        $this->availableAt = $now + self::COOLDOWN_DURATION;
        $this->lastGoldAt = $now;
        $this->candidates = [];
    }
}