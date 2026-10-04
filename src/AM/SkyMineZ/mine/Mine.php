<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Mine
{
    private MineBox $mineBox;

    /** @var array<MineBlock> */
    private array $mineBlocks = [];

    public function __construct(Vector3 $position1, Vector3 $position2, World $world)
    {
        $this->mineBox = new MineBox($position1, $position2, $world);
    }

    public function getMineBox(): MineBox
    {
        return $this->mineBox;
    }

    public function addBlock(MineBlock $block): void
    {
        $this->mineBlocks[] = $block;
    }

    /** @param array<MineBlock> $blocks */
    public function setBlocks(array $blocks): void
    {
        $this->mineBlocks = $blocks;
    }

    /** @return array<MineBlock> */
    public function getBlocks(): array
    {
        return $this->mineBlocks;
    }

    public function reset(): void
    {
        $this->mineBox->apply($this->mineBlocks);
    }

    public function isIn(Vector3 $pos): bool
    {
        return $this->mineBox->isIn($pos);
    }
}