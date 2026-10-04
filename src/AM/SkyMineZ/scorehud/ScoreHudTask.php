<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use pocketmine\scheduler\Task;

class ScoreHudTask extends Task
{
    public function __construct(
        private ScoreHud $scoreHud
    ) {
    }

    public function onRun(): void
    {
        $this->scoreHud->tick();
    }
}