<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

final class MultiLineTextParticle
{
    public const LINE_SPACING = 0.25;

    /** @var array<int, TextParticle> */
    private array $lines = [];

    /** @var list<string> */
    private array $texts = [];

    private bool $spawned = false;

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

    public function setLine(
        int $id,
        string $text
    ): void {
        if ($id < 0) {
            return;
        }

        $this->texts[$id] = $text;

        ksort($this->texts);
        $this->texts = array_values($this->texts);

        if (!$this->spawned) {
            return;
        }

        $this->rebuild();
    }

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
    ): void {
        if (!isset($this->texts[$id])) {
            return;
        }

        unset($this->texts[$id]);

        $this->texts = array_values(
            $this->texts
        );

        if ($this->spawned) {
            $this->rebuild();
        }
    }

    /**
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
        $this->lines = [];

        if ($wasSpawned) {
            $this->spawn();
        }
    }

    public function spawn(
        ?Player $player = null
    ): void {
        /*
         * Sending to one player must not despawn the
         * lines for everyone else.
         */
        if ($player !== null) {
            foreach ($this->texts as $id => $text) {
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
        foreach ($this->lines as $line) {
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
        return $this->texts;
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
        if ($this->spawned) {
            foreach ($this->lines as $line) {
                $line->deSpawn();
            }
        }

        $this->lines = [];

        foreach ($this->texts as $id => $text) {
            $this->lines[$id] = $this->createLine(
                $id,
                $text
            );

            $this->lines[$id]->spawn();
        }

        $this->spawned = true;
    }

    private function createLine(
        int $id,
        string $text
    ): TextParticle {
        $position = new Vector3(
            $this->basePosition->x,
            $this->basePosition->y -
            ($id * self::LINE_SPACING),
            $this->basePosition->z
        );

        return new TextParticle(
            $text,
            $position,
            $this->world
        );
    }
}