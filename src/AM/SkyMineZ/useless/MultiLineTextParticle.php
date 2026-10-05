<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * A stack of floating text lines anchored to one world position.
 *
 * Lines grow downwards from the anchor: line 0 sits on the anchor and line n
 * sits `LINE_SPACING * n` blocks lower. Callers that need the stack to grow
 * upwards should raise the base position themselves, which is what
 * {@link OutpostInfo::computeBasePosition()} does.
 *
 * The whole stack is rebuilt only when the text actually changes, because every
 * rebuild despawns and respawns each line, which is a visible flicker and costs
 * one packet per line.
 */
final class MultiLineTextParticle
{
    public const LINE_SPACING = 0.25;

    /** @var array<int, TextParticle> */
    private array $lines = [];

    /** @var array<int, string> */
    private array $texts = [];

    private bool $spawned = false;

    /**
     * @param list<string> $lines
     */
    public function __construct(
        private Vector3 $basePosition,
        private World $world,
        array $lines = []
    ) {
        $this->texts = array_values(
            array_map(
                static fn(mixed $text): string => (string) $text,
                $lines
            )
        );
    }

    /**
     * Creates or replaces a single line.
     *
     * Missing lines are padded with an empty string so that setting line 3
     * without touching lines 0-2 does not leave holes in the hologram.
     */
    public function setLine(
        int $id,
        string $text
    ): void {
        if ($id < 0) {
            return;
        }

        if (
            ($this->texts[$id] ?? null) === $text
        ) {
            return;
        }

        $this->texts[$id] = $text;

        ksort($this->texts);

        $this->repack();

        if ($this->spawned) {
            $this->rebuild();
        }
    }

    /**
     * Appends a line and returns its index.
     */
    public function addLine(
        string $text
    ): int {
        $id = count($this->texts);

        $this->texts[] = $text;

        if ($this->spawned) {
            $this->rebuild();
        }

        return $id;
    }

    public function removeLine(
        int $id
    ): void
    {
        if (!isset($this->texts[$id])) {
            return;
        }

        unset($this->texts[$id]);

        $this->repack();

        if ($this->spawned) {
            $this->rebuild();
        }
    }

    /**
     * Replaces every line at once. Does nothing when the result is identical,
     * which keeps the 10-minute leaderboard refresh from flickering.
     *
     * @param list<string> $lines
     */
    public function setLines(
        array $lines
    ): void {
        $lines = array_values(
            array_map(
                static fn(mixed $text): string => (string) $text,
                $lines
            )
        );

        if ($this->texts === $lines) {
            return;
        }

        $wasSpawned = $this->spawned;

        if ($wasSpawned) {
            $this->deSpawn();
        }

        $this->texts = $lines;

        if ($wasSpawned) {
            $this->spawn();
        }
    }

    /**
     * Shows the stack. Passing a player must not tear the hologram down for
     * everyone else, so in that case only the missing lines are created and
     * spawned for that one player.
     */
    public function spawn(?Player $player = null): void
    {
        if ($player !== null) {
            foreach (
                $this->texts as $id => $text
            ) {
                if (!isset($this->lines[$id])) {
                    $this->lines[$id] = $this->createLine(
                        $id,
                        $text
                    );
                }

                $this->lines[$id]->spawn($player);
            }

            return;
        }

        $this->rebuild();
    }

    public function deSpawn(): void
    {
        foreach (
            $this->lines as $line
        ) {
            $line->deSpawn();
        }

        $this->spawned = false;
    }

    public function isSpawned(): bool
    {
        return $this->spawned;
    }

    public function setPosition(
        Vector3 $position
    ): void {
        $this->basePosition = $position;

        if ($this->spawned) {
            $this->rebuild();
        }
    }

    public function getPosition(): Vector3
    {
        return $this->basePosition;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    /**
     * @return list<string>
     */
    public function getLines(): array
    {
        return array_values(
            $this->texts
        );
    }

    /**
     * Closes gaps left by setLine()/removeLine() so line indexes stay
     * consecutive.
     */
    private function repack(): void
    {
        $this->texts = array_values(
            $this->texts
        );
    }

    /**
     * @return array<int, TextParticle>
     */
    public function getParticles(): array
    {
        return $this->lines;
    }

    private function rebuild(): void
    {
        foreach (
            $this->lines as $line
        ) {
            $line->deSpawn();
        }


        $this->lines = [];

        foreach (
            $this->texts as $id => $text
        ) {
            $line = $this->createLine($id, $text);
            $line->spawn();

            $this->lines[$id] = $line;
        }

        $this->spawned = true;
    }

    private function createLine(
        int $id,
        string $text
    ): TextParticle {
        return new TextParticle(
            $text,
            new Vector3(
                $this->basePosition->x,
                $this->basePosition->y - ($id * self::LINE_SPACING),
                $this->basePosition->z
            ),
            $this->world
        );
    }
}