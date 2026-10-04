<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class CollisionBox
{
    private World $world;

    private Vector3 $pos1;

    private Vector3 $pos2;

    public function __construct(Vector3 $pos1, Vector3 $pos2, World $world)
    {
        $this->world = $world;
        $this->pos1 = $pos1;
        $this->pos2 = $pos2;
    }

    public function isIn(Vector3 $pos): bool
    {
        $minX = min($this->pos1->x, $this->pos2->x);
        $minY = min($this->pos1->y, $this->pos2->y);
        $minZ = min($this->pos1->z, $this->pos2->z);

        $maxX = max($this->pos1->x, $this->pos2->x);
        $maxY = max($this->pos1->y, $this->pos2->y);
        $maxZ = max($this->pos1->z, $this->pos2->z);

        if ($pos->x < $minX || $pos->x > $maxX) {
            return false;
        }

        if ($pos->y < $minY || $pos->y > $maxY) {
            return false;
        }

        if ($pos->z < $minZ || $pos->z > $maxZ) {
            return false;
        }

        return true;
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