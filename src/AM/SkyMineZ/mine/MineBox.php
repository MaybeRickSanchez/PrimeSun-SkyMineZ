<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\useless\CollisionBox;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * The cuboid a mine fills, plus the geometry helpers the refill needs.
 */
class MineBox extends CollisionBox
{
    public function __construct(
        Vector3 $pos1,
        Vector3 $pos2,
        World $world
    ) {
        parent::__construct(
            $pos1,
            $pos2,
            $world
        );
    }

    public function getVolume(): int
    {
        return $this->getSizeX() * $this->getSizeY() * $this->getSizeZ();
    }

    public function getSizeX(): int
    {
        return $this->getMaxX() - $this->getMinX() + 1;
    }

    public function getSizeY(): int
    {
        return $this->getMaxY() - $this->getMinY() + 1;
    }

    public function getSizeZ(): int
    {
        return $this->getMaxZ() - $this->getMinZ() + 1;
    }

    /**
     * Builds the shuffled block pool for a refill.
     *
     * Percentages do not have to add up to 100: every entry is scaled by its
     * share of the total, and the slots lost to rounding are filled with random
     * entries so the visible distribution stays close to what was configured
     * instead of always biasing towards the first block.
     *
     * @param list<MineBlock> $blocks
     *
     * @return list<Block>
     */
    public function buildPool(
        array $blocks,
        int $volume
    ): array {
        if (
            $blocks === []
            || $volume <= 0
        ) {
            return [];
        }

        $entries = [];

        foreach ($blocks as $block) {
            $percent = $block->getPercent();

            if ($percent <= 0) {
                continue;
            }

            $entries[] = [
                'block' => $block->getBlock(),
                'weight' => $percent
            ];
        }

        if ($entries === []) {
            return [];
        }

        $totalWeight = 0;

        foreach ($entries as $entry) {
            $totalWeight += $entry['weight'];
        }

        if ($totalWeight <= 0) {
            return [];
        }

        $last = count($entries) - 1;
        $pool = [];

        foreach ($entries as $entry) {
            $count = (int) floor(
                $volume * ($entry['weight'] / $totalWeight)
            );

            for (
                $i = 0;
                $i < $count;
                ++$i
            ) {
                // Clone: sharing one Block instance across slots aliases
                // state and breaks fills that mutate the block.
                $pool[] = clone $entry['block'];
            }
        }

        while (count($pool) < $volume) {
            $pool[] = clone $entries[mt_rand(
                0,
                $last
            )]['block'];
        }

        shuffle($pool);

        return $pool;
    }

    /**
     * Teleports anybody standing inside the box to just above it.
     *
     * Called right before a refill starts, so nobody ends up sealed inside the
     * fresh blocks.
     */
    public function evacuatePlayers(): void
    {
        $world = $this->getWorld();
        $top = $this->getMaxY() + 2;

        foreach ($world->getPlayers() as $player) {
            $position = $player->getPosition();

            if ($player->getWorld() !== $world || !$this->isIn($position)) {
                continue;
            }

            $target = new Position(
                $position->x,
                $top,
                $position->z,
                $world
            );

            // Keep yaw/pitch so evacuation does not snap the player's view.
            $location = $player->getLocation();

            $target = new \pocketmine\entity\Location(
                $target->x,
                $target->y,
                $target->z,
                $world,
                $location->getYaw(),
                $location->getPitch()
            );

            $player->teleport($target);
        }
    }
}