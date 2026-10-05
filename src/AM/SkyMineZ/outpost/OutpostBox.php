<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\useless\CollisionBox;
use pocketmine\player\Player;

/**
 * The capture zone of an outpost.
 *
 * Outposts are cuboids like mines, so the geometry comes from
 * {@link CollisionBox}; this subclass only adds the player oriented helpers.
 */
class OutpostBox extends CollisionBox
{
    /**
     * First player standing inside the box, or null when it is empty.
     *
     * Returned without loading the full name list: the caller usually only wants
     * to know whether anybody is capturing.
     */
    public function getOccupyingPlayer(): ?Player
    {
        foreach (
            $this->getWorld()->getPlayers() as $player
        ) {
            if ($this->isIn($player->getPosition())) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Names of everybody standing inside the box, in world iteration order.
     *
     * @return list<string>
     */
    public function getOccupants(): array
    {
        $names = [];

        foreach (
            $this->getWorld()->getPlayers() as $player
        ) {
            if ($this->isIn($player->getPosition())) {
                $names[] = $player->getName();
            }
        }

        return $names;
    }

    public function isOccupied(): bool
    {
        foreach (
            $this->getWorld()->getPlayers() as $player
        ) {
            if ($this->isIn($player->getPosition())) {
                return true;
            }
        }

        return false;
    }

    public function countOccupants(): int
    {
        $count = 0;

        foreach (
            $this->getWorld()->getPlayers() as $player
        ) {
            if ($this->isIn($player->getPosition())) {
                ++$count;
            }
        }

        return $count;
    }
}