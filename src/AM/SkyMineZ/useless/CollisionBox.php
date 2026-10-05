<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * An axis aligned cuboid between two corners.
 *
 * The corners are normalised on construction, so {@link isIn()} costs six float
 * comparisons instead of six min()/max() calls. Mines and outposts run this check
 * against every player on every tick, which makes the difference visible on a
 * busy server.
 */
class CollisionBox
{
    private float $minX;

    private float $minY;

    private float $minZ;

    private float $maxX;

    private float $maxY;

    private float $maxZ;

    public function __construct(
        private Vector3 $pos1,
        private Vector3 $pos2,
        private World $world
    ) {
        $this->minX = min(
            $pos1->x,
            $pos2->x
        );
        $this->minY = min(
            $pos1->y,
            $pos2->y
        );
        $this->minZ = min(
            $pos1->z,
            $pos2->z
        );

        $this->maxX = max(
            $pos1->x,
            $pos2->x
        );
        $this->maxY = max(
            $pos1->y,
            $pos2->y
        );
        $this->maxZ = max(
            $pos1->z,
            $pos2->z
        );
    }

    public function isIn(Vector3 $pos): bool
    {
        return $pos->x >= $this->minX
            && $pos->x <= $this->maxX
            && $pos->y >= $this->minY
            && $pos->y <= $this->maxY
            && $pos->z >= $this->minZ
            && $pos->z <= $this->maxZ;
    }

    private ?Vector3 $center = null;

    /**
     * Middle of the box. Handy for picking "the player furthest from the middle"
     * and for placing a label above a mine.
     *
     * Memoized: boxes are immutable after construction, so the center never
     * changes and callers like the per-second outpost scan get it for free.
     */
    public function getCenter(): Vector3
    {
        return $this->center ??= new Vector3(
            ($this->minX + $this->maxX) / 2,
            ($this->minY + $this->maxY) / 2,
            ($this->minZ + $this->maxZ) / 2
        );
    }

    /**
     * Normalized integer bounds. MineFillTask reads these every tick instead of
     * re-running min()/max() over the corners, which also keeps the per-tick
     * path free of method-call chains into getPos1()/getPos2().
     */
    public function getMinX(): int
    {
        return (int) $this->minX;
    }

    public function getMinY(): int
    {
        return (int) $this->minY;
    }

    public function getMinZ(): int
    {
        return (int) $this->minZ;
    }

    public function getMaxX(): int
    {
        return (int) $this->maxX;
    }

    public function getMaxY(): int
    {
        return (int) $this->maxY;
    }

    public function getMaxZ(): int
    {
        return (int) $this->maxZ;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getPos1(): Vector3
    {
        return $this->pos1;
    }

    public function getPos2(): Vector3
    {
        return $this->pos2;
    }
}