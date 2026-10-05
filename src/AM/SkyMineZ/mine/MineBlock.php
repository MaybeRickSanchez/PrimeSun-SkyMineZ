<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

/**
 * One entry of a mine's block list: a block and the percentage of the mine's
 * volume it should occupy.
 *
 * Blocks are stored in mines.json as base64 Bedrock block state NBT, which keeps
 * the full block state (and therefore any NBT the block carries) intact across
 * restarts, which a plain name would not.
 */
final class MineBlock
{
    private int $percent;

    private Block $block;

    /**
     * @throws InvalidArgumentException when $percent is not between 1 and 100
     */
    public function __construct(
        int $percent,
        Block $block
    ) {
        $this->setPercent($percent);
        $this->block = $block;
    }

    public function getPercent(): int
    {
        return $this->percent;
    }

    public function setPercent(int $percent): self
    {
        if (
            $percent < 1
            || $percent > 100
        ) {
            throw new InvalidArgumentException(
                'A mine block percentage must be between 1 and 100.'
            );
        }

        $this->percent = $percent;

        return $this;
    }

    public function getBlock(): Block
    {
        return $this->block;
    }

    public function setBlock(Block $block): self
    {
        $this->block = $block;

        return $this;
    }

    public function getName(): string
    {
        return $this->block->getName();
    }

    /**
     * @return array{block: string, percent: int}
     */
    public function toArray(): array
    {
        return [
            'block' => self::encode($this->block),
            'percent' => $this->percent
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data
    ): ?self {
        $encoded = $data['block'] ?? null;

        if (
            !is_string($encoded)
            || !isset($data['percent'])
            || !is_numeric($data['percent'])
        ) {
            return null;
        }

        $block = self::decode($encoded);

        if ($block === null) {
            return null;
        }

        $percent = (int) $data['percent'];

        if (
            $percent < 1
            || $percent > 100
        ) {
            return null;
        }

        return new self($percent, $block);
    }

    private static function encode(Block $block): string
    {
        $stateData = GlobalBlockStateHandlers::getSerializer()->serialize(
            $block->getStateId()
        );

        $binary = (new LittleEndianNbtSerializer())->write(
            new TreeRoot($stateData->toNbt())
        );

        return base64_encode($binary);
    }

    private static function decode(string $encoded): ?Block
    {
        $binary = base64_decode(
            $encoded,
            true
        );

        if ($binary === false) {
            return null;
        }

        try {
            $root = (new LittleEndianNbtSerializer())->read(
                $binary
            );

            $stateId = GlobalBlockStateHandlers::getDeserializer()->deserialize(
                BlockStateData::fromNbt(
                    $root->mustGetCompoundTag()
                )
            );

            return RuntimeBlockStateRegistry::getInstance()->fromStateId(
                $stateId
            );
        } catch (\Throwable) {
            return null;
        }
    }
}