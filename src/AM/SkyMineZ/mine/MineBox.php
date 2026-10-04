<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\CollisionBox;
use pocketmine\math\Vector3;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;
use pocketmine\scheduler\Task;

class MineBox extends CollisionBox
{
    private ?TaskHandler $activeTask = null;

    public function __construct(Vector3 $pos1, Vector3 $pos2, World $world)
    {
        parent::__construct($pos1, $pos2, $world);
    }

    public function isApplying(): bool
    {
        return $this->activeTask !== null && !$this->activeTask->isCancelled();
    }

    /**
     * @param array<MineBlock> $blocks
     */
    public function apply(array $blocks): void
    {
        if (empty($blocks)) {
            return;
        }

        // Cancel any previous run so two fills never overlap
        if ($this->activeTask !== null) {
            $this->activeTask->cancel();
            $this->activeTask = null;
        }

        $world = $this->getWorld();

        $minX = (int) min($this->getPos1()->x, $this->getPos2()->x);
        $minY = (int) min($this->getPos1()->y, $this->getPos2()->y);
        $minZ = (int) min($this->getPos1()->z, $this->getPos2()->z);

        $maxX = (int) max($this->getPos1()->x, $this->getPos2()->x);
        $maxY = (int) max($this->getPos1()->y, $this->getPos2()->y);
        $maxZ = (int) max($this->getPos1()->z, $this->getPos2()->z);

        $layerSize = ($maxX - $minX + 1) * ($maxZ - $minZ + 1);
        $total = $layerSize * ($maxY - $minY + 1);

        if ($total <= 0) {
            return;
        }

        $pool = [];
        foreach ($blocks as $mineBlock) {
            $count = (int) floor($total * $mineBlock->getPercent() / 100);
            if ($count <= 0) {
                continue;
            }
            $pool = array_merge($pool, array_fill(0, $count, $mineBlock->getBlock()));
        }

        // Pad with the first block if percentages don't sum to 100
        if (count($pool) < $total) {
            $pool = array_merge(
                $pool,
                array_fill(0, $total - count($pool), $blocks[0]->getBlock())
            );
        }

        shuffle($pool);

        // Teleport any player standing inside the box above it
        foreach ($world->getPlayers() as $player) {
            if ($this->isIn($player->getPosition())) {
                $pos = $player->getPosition();
                $player->teleport(new Vector3($pos->x, $maxY + 2, $pos->z));
            }
        }

        $task = new class(
            $world,
            $pool,
            $minX,
            $maxX,
            $maxY,
            $minY,
            $minZ,
            $maxZ
        ) extends Task {
            private int $offset = 0;

            public function __construct(
                private World $world,
                private array $pool,
                private int $minX,
                private int $maxX,
                private int $currentY,
                private int $minY,
                private int $minZ,
                private int $maxZ
            ) {}

            public function onRun(): void
            {
                $poolSize = count($this->pool);

                for ($x = $this->minX; $x <= $this->maxX; $x++) {
                    for ($z = $this->minZ; $z <= $this->maxZ; $z++) {
                        if ($this->offset >= $poolSize) {
                            $this->getHandler()->cancel();
                            return;
                        }

                        $block = $this->pool[$this->offset++];
                        $this->world->setBlockAt($x, $this->currentY, $z, $block, false);
                    }
                }

                $this->currentY--;

                if ($this->currentY < $this->minY) {
                    $this->getHandler()->cancel();
                }
            }
        };

        $this->activeTask = Main::getInstance()
            ->getScheduler()
            ->scheduleRepeatingTask($task, 1);
    }
}