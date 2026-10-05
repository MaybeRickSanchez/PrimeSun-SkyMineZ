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
 * Changed lines are updated in place with a single packet each: no despawn /
 * respawn pair, no flicker. A full rebuild happens only on structural changes
 * (line count or positions), which are rare compared to text ticks.
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
     *
     * Only the touched line is re-sent, and only when its text actually changed:
     * a countdown tick costs one packet instead of a full despawn/respawn cycle
     * over every line.
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

        $count = count($this->texts);

        $this->texts[$id] = $text;

        ksort($this->texts);

        $this->repack();

        /*
         * repack() closes gaps, so an out-of-range id lands on the last slot;
         * anything else keeps its index.
         */
        $index = $id < $count ? $id : $count;

        /*
         * Line objects outlive a despawn, so their existence — not the spawned
         * flag — decides whether there is something to update. This also keeps
         * per-player-only lines fresh: they are re-sent to the player they were
         * shown to, instead of going stale until the next global rebuild.
         */
        if (isset($this->lines[$index])) {
            $this->lines[$index]->setText($text);
        } elseif ($this->spawned) {
            $line = $this->createLine($index, $text);
            $line->spawn();

            $this->lines[$index] = $line;
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
     * When only the text changed (same line count), each differing line is
     * updated in place instead of tearing the whole stack down: a 14-line board
     * whose first row changed costs 1 packet instead of 28.
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

        $oldCount = count($this->texts);

        $this->texts = $lines;

        if (
            !$this->spawned
            || count($lines) !== $oldCount
        ) {
            /*
             * Structural change (or nothing on screen yet): positions shift, so
             * a single rebuild is the correct primitive here.
             */
            if ($this->spawned) {
                $this->rebuild();
            }

            return;
        }

        foreach (
            $lines as $id => $text
        ) {
            if (isset($this->lines[$id])) {
                $this->lines[$id]->setText($text);
            }
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