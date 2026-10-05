<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use Closure;
use pocketmine\block\Block;
use pocketmine\scheduler\Task;

/**
 * One tick of a mine refill: writes up to `blocksPerTick` blocks and stops when
 * the mine is full again.
 *
 * The pool is generated once per refill and shared with the task, so the block
 * distribution stays consistent for the whole fill even if someone edits the
 * block list while the mine is being rebuilt.
 *
 * Coordinates are derived arithmetically from the block offset instead of
 * materialising the whole volume: a 50,000 block mine would otherwise allocate
 * 50,000 temporary arrays before the first block was written.
 *
 * This is the single biggest source of lag the plugin has to defend against, so
 * the per-tick budget is what keeps a large mine from freezing the server.
 */
final class MineFillTask extends Task
{
    private readonly Closure $onComplete;

    /** @var list<Block> */
    private array $pool;

    private int $offset = 0;

    /**
     * @param list<Block> $pool one entry per volume slot
     * @param (callable(): void)|null $onComplete
     */
    public function __construct(
        private MineBox $box,
        array $pool,
        private int $blocksPerTick = 3000,
        ?callable $onComplete = null
    ) {
        $this->pool = $pool;

        $this->onComplete = $onComplete === null
            ? static function(): void {
            }
            : (
                $onComplete instanceof Closure
                    ? $onComplete
                    : Closure::fromCallable($onComplete)
            );
    }

    public function onRun(): void
    {
        $volume = $this->box->getVolume();

        $sizeZ = $this->box->getSizeZ();
        $layerSize = $this->box->getSizeX() * $sizeZ;

        $minX = $this->box->getMinX();
        $minZ = $this->box->getMinZ();
        $maxY = $this->box->getMaxY();

        $world = $this->box->getWorld();
        $pool = $this->pool;

        $limit = min(
            $volume,
            $this->offset + max(1, $this->blocksPerTick)
        );

        for (
            $index = $this->offset;
            $index < $limit;
            ++$index
        ) {
            $layer = intdiv(
                $index,
                $layerSize
            );
            $within = $index % $layerSize;

            $world->setBlockAt(
                $minX + intdiv($within, $sizeZ),
                $maxY - $layer,
                $minZ + ($within % $sizeZ),
                $pool[$index],
                false
            );
        }

        $this->offset = $limit;

        if ($this->offset < $volume) {
            return;
        }

        $this->getHandler()?->cancel();

        ($this->onComplete)();
    }

    /**
     * 0.0 right after the refill started, 1.0 once the mine is full again.
     */
    public function getProgress(): float
    {
        $volume = $this->box->getVolume();

        return $volume > 0
            ? min(1.0, $this->offset / $volume)
            : 1.0;
    }
}
