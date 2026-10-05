<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use Closure;
use pocketmine\block\Block;
use pocketmine\scheduler\Task;
use pocketmine\world\World;

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
     * Loop-invariant geometry, snapshotted once so onRun() pays zero method
     * calls for bounds on every tick of the refill.
     */
    private int $volume;

    private int $layerSize;

    private int $sizeZ;

    private int $minX;

    private int $minZ;

    private int $maxY;

    private World $world;

    private int $budget;

    /**
     * @param list<Block> $pool one entry per volume slot
     * @param (callable(): void)|null $onComplete
     */
    public function __construct(
        MineBox $box,
        array $pool,
        int $blocksPerTick = 3000,
        ?callable $onComplete = null
    ) {
        $this->pool = $pool;

        $this->sizeZ = $box->getSizeZ();
        $this->layerSize = $box->getSizeX() * $this->sizeZ;
        $this->minX = $box->getMinX();
        $this->minZ = $box->getMinZ();
        $this->maxY = $box->getMaxY();
        $this->world = $box->getWorld();
        $this->volume = $this->layerSize * $box->getSizeY();
        $this->budget = max(1, $blocksPerTick);

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
        $pool = $this->pool;
        $volume = $this->volume;

        $limit = $this->offset + $this->budget;

        if ($limit > $volume) {
            $limit = $volume;
        }

        $layerSize = $this->layerSize;
        $sizeZ = $this->sizeZ;
        $minX = $this->minX;
        $minZ = $this->minZ;
        $maxY = $this->maxY;
        $world = $this->world;

        for (
            $index = $this->offset;
            $index < $limit;
            ++$index
        ) {
            $within = $index % $layerSize;

            $world->setBlockAt(
                $minX + intdiv($within, $sizeZ),
                $maxY - intdiv($index, $layerSize),
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
        return $this->volume > 0
            ? min(1.0, $this->offset / $this->volume)
            : 1.0;
    }
}
