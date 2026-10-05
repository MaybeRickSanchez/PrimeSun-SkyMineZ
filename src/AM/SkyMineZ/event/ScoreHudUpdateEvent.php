<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

/**
 * Raised right before the sidebar is (re)built for a player. Cancelling it
 * suppresses that update, which lets another plugin draw its own scoreboard in
 * the same slot without fighting SkyMineZ over every refresh tick.
 */
final class ScoreHudUpdateEvent extends CancellableSkyMineEvent
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        private string $playerName,
        private string $title,
        private array $lines
    ) {
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }
}