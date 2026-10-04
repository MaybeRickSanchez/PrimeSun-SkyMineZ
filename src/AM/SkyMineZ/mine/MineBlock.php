<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use pocketmine\block\Block;

class MineBlock
{

    private int $percent;

    private Block $block;

    public function __construct(int $percent, Block $block)
    {
        $this->percent = $percent;
        $this->block = $block;
    }

    public function getPercent(): int{
        return $this->percent;
    }

    public function getBlock(): Block{
        return $this->block;
    }

}