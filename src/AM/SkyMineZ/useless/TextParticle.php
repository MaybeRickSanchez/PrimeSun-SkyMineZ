<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\world\particle\FloatingTextParticle;

/**
 * A floating text hologram built from one Bedrock `FloatingTextParticle`.
 *
 * PocketMine entities do not expose name tags for arbitrary entities, so a
 * particle is the reliable way to show text that is attached to a world
 * position. Both spawning and hiding can be scoped to a single player, which is
 * what `spawn($player)` / `deSpawn()` are used for when the hologram belongs to
 * a leaderboard that only some players should see.
 */
final class TextParticle
{
    private FloatingTextParticle $particle;

    private bool $spawned = false;

    /**
     * Who the text was last shown to: null means the whole world. Remembered so
     * that a text change re-sends to the same audience instead of leaking a
     * per-player hologram to everybody.
     */
    private ?Player $audience = null;

    public function __construct(
        string $text,
        private Vector3 $position,
        private World $world
    ) {
        $this->particle = new FloatingTextParticle($text);
    }

    public function setText(string $text): void
    {
        if ($this->particle->getText() === $text) {
            return;
        }

        $this->particle->setText($text);

        if ($this->spawned) {
            /*
             * Re-send in place: no despawn/respawn pair, so the client swaps the
             * text with one packet and no flicker.
             */
            $this->spawn($this->audience);
        }
    }

    public function getText(): string
    {
        return $this->particle->getText();
    }

    /**
     * Shows the text to everyone in the world, or to $player only.
     */
    public function spawn(?Player $player = null): void
    {
        $this->particle->setInvisible(false);

        $this->world->addParticle(
            $this->position,
            $this->particle,
            $player !== null ? [$player] : null
        );

        $this->audience = $player;
        $this->spawned = true;
    }

    /**
     * Hides the text. The invisible marker has to be broadcast to the same
     * audience the text was shown to, otherwise players who joined afterwards
     * keep seeing a stale hologram.
     */
    public function deSpawn(?Player $player = null): void
    {
        if (!$this->spawned) {
            return;
        }

        $this->particle->setInvisible();

        $this->world->addParticle(
            $this->position,
            $this->particle,
            $player !== null ? [$player] : null
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

    public function setPosition(Vector3 $position): void
    {
        if ($position->equals($this->position)) {
            return;
        }

        $this->position = $position;
    }

    public function getWorld(): World
    {
        return $this->world;
    }
}