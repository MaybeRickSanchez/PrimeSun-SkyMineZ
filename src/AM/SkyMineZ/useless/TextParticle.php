<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\world\particle\FloatingTextParticle;

final class TextParticle
{
    private FloatingTextParticle $particle;

    private bool $spawned = false;

    public function __construct(
        string $text,
        private Vector3 $position,
        private World $world
    ) {
        $this->particle = new FloatingTextParticle($text);
    }

    public function setText(string $text): void
    {
        $this->particle->setText($text);

        if ($this->spawned) {
            $this->spawn();
        }
    }

    public function spawn(?Player $player = null): void
    {
        $this->particle->setInvisible(false);

        $this->world->addParticle(
            $this->position,
            $this->particle,
            $player !== null ? [$player] : null
        );

        $this->spawned = true;
    }

    public function deSpawn(): void
    {
        if (!$this->spawned) {
            return;
        }

        $this->particle->setInvisible();

        $this->world->addParticle(
            $this->position,
            $this->particle
        );

        $this->spawned = false;
    }

    public function isSpawned(): bool
    {
        return $this->spawned;
    }

    public function getPosition(): Vector3
    {
        return $this->position;
    }

    public function getWorld(): World
    {
        return $this->world;
    }
}