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
     *
     * A global spawn stays global: later per-player top-ups never overwrite
     * this, otherwise a single join would redirect every future update to one
     * player and leak the hologram for everyone else.
     */
    private ?Player $audience = null;

    /** @var array<string, true> lower-case names already shown a per-player copy */
    private array $extraAudiences = [];

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
             * text with one packet and no flicker. Global stays global; a
             * per-player hologram goes back to the same player.
             */
            $this->spawn($this->audience);

            // Per-player top-ups added after a global spawn need the new text
            // too; they share the same particle text so re-sending globally
            // already covers them (global addParticle reaches everyone).
        }
    }

    public function getText(): string
    {
        return $this->particle->getText();
    }

    /**
     * Shows the text to everyone in the world, or to $player only.
     *
     * Per-player calls are additive: they never steal a global hologram's
     * audience. despawn/respawn cycles stay per-audience for the same reason.
     */
    public function spawn(?Player $player = null): void
    {
        $this->particle->setInvisible(false);

        $this->world->addParticle(
            $this->position,
            $this->particle,
            $player !== null ? [$player] : null
        );

        if ($player === null) {
            $this->audience = null;
            $this->extraAudiences = [];
        } elseif ($this->audience !== null || !$this->spawned) {
            // First show (per-player only), or already per-player: remember it.
            $this->audience = $player;
        } else {
            // Global hologram plus one late joiner: remember them so a later
            // global despawn can hide their copy too, but keep audience global.
            $this->extraAudiences[strtolower($player->getName())] = true;
        }

        $this->spawned = true;
    }

    /**
     * Hides the text. The invisible marker has to be broadcast to the same
     * audience the text was shown to, otherwise players who joined afterwards
     * keep seeing a stale hologram.
     *
     * Hiding one player never clears a global hologram: only a global despawn
     * resets the spawned flag.
     */
    public function deSpawn(?Player $player = null): void
    {
        if (!$this->spawned) {
            return;
        }

        if ($player !== null && $this->audience === null) {
            // Hide just this player's copy, keep the global hologram alive.
            $this->particle->setInvisible();

            $this->world->addParticle(
                $this->position,
                $this->particle,
                [$player]
            );

            unset($this->extraAudiences[strtolower($player->getName())]);

            $this->particle->setInvisible(false);

            return;
        }

        $this->particle->setInvisible();

        $this->world->addParticle(
            $this->position,
            $this->particle,
            $player !== null ? [$player] : null
        );

        $this->spawned = false;
        $this->extraAudiences = [];
    }

    public function isSpawned(): bool
    {
        return $this->spawned;
    }

    public function getPosition(): Vector3
    {
        return $this->position;
    }

    public function setPosition(Vector3 $position, ?World $world = null): void
    {
        $world ??= $this->world;

        if ($position->equals($this->position) && $world === $this->world) {
            return;
        }

        $wasSpawned = $this->spawned;
        $audience = $this->audience;

        if ($wasSpawned) {
            // Hide from the old spot before moving, using the same audience
            // the text was shown to.
            $this->deSpawn($audience);
        }

        $this->position = $position;
        $this->world = $world;

        /*
         * Moving without re-sending would leave the client showing the text at
         * the old spot, so a spawned particle follows its position the same way
         * setText() follows its content.
         */
        if ($wasSpawned) {
            $this->spawn($audience);
        }
    }

    public function getWorld(): World
    {
        return $this->world;
    }
}