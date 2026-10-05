<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Per-player cuboid selection, the same workflow world editors use:
 * `/skymine pos1` and `/skymine pos2` remember two corners, and the cuboid
 * commands then read them.
 *
 * Selections are session only; there is no reason to persist them.
 */
final class SelectionManager
{
    /** @var array<string, array{pos1: Position|null, pos2: Position|null}> */
    private array $selections = [];

    public function setPos1(
        Player $player,
        Position $position
    ): void {
        $name = $this->key($player);

        $this->selections[$name]['pos1'] = $position;
    }

    public function setPos2(
        Player $player,
        Position $position
    ): void {
        $name = $this->key($player);

        $this->selections[$name]['pos2'] = $position;
    }

    public function getPos1(
        Player $player
    ): ?Position {
        return $this->selections[$this->key(
            $player
        )]['pos1'] ?? null;
    }

    public function getPos2(
        Player $player
    ): ?Position {
        return $this->selections[$this->key(
            $player
        )]['pos2'] ?? null;
    }

    public function hasBoth(
        Player $player
    ): bool {
        return $this->getPos1(
            $player
        ) !== null && $this->getPos2(
            $player
        ) !== null;
    }

    /**
     * Both corners in world space, or null when the selection is incomplete or
     * the corners are in different worlds.
     *
     * @return array{Position, Position}|null
     */
    public function getRegion(
        Player $player
    ): ?array {
        $pos1 = $this->getPos1($player);
        $pos2 = $this->getPos2($player);

        if (
            $pos1 === null
            || $pos2 === null
        ) {
            return null;
        }

        if ($pos1->getWorld() !== $pos2->getWorld()) {
            return null;
        }

        return [$pos1, $pos2];
    }

    /**
     * The cuboid as plain vectors in the players' world, ready to hand to a
     * mine or an outpost.
     *
     * @return array{Vector3, Vector3}|null
     */
    public function getVectors(
        Player $player
    ): ?array {
        $region = $this->getRegion($player);

        if ($region === null) {
            return null;
        }

        return [
            $region[0]->asVector3(),
            $region[1]->asVector3()
        ];
    }

    public function clear(
        Player $player
    ): void {
        unset(
            $this->selections[$this->key($player)]
        );
    }

    private function key(
        Player $player
    ): string {
        return strtolower(
            $player->getName()
        );
    }
}