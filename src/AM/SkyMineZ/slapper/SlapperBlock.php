<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use AM\SkyMineZ\useless\TextParticle;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\Position;
use pocketmine\world\World;
use Throwable;

final class SlapperBlock
{
    private TextParticle $textParticle;

    public function __construct(
        private string $name,
        private Position $position,
        private Block $block,
        private string $slapperName
    ) {
        $this->textParticle = new TextParticle(
            $name,
            $position->add(
                0.5,
                1.2,
                0.5
            ),
            $position->getWorld()
        );
    }

    public function spawn(): void
    {
        $this->position
            ->getWorld()
            ->setBlock(
                $this->position,
                clone $this->block
            );

        if (!$this->textParticle->isSpawned()) {
            $this->textParticle->spawn();
        }
    }

    public function despawn(): void
    {
        $this->textParticle->deSpawn();
    }

    public function remove(): void
    {
        $this->despawn();

        $this->position
            ->getWorld()
            ->setBlock(
                $this->position,
                VanillaBlocks::AIR()
            );
    }

    public function spawnText(
        Player $player
    ): void {
        $this->textParticle->spawn(
            $player
        );
    }

    public function getTextParticle(): TextParticle
    {
        return $this->textParticle;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(
        string $name
    ): self {
        $this->name = $name;

        $this->textParticle->setText(
            $name
        );

        return $this;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getWorld(): World
    {
        return $this->position->getWorld();
    }

    public function getBlock(): Block
    {
        return clone $this->block;
    }

    public function setBlock(
        Block $block
    ): self {
        $this->block = clone $block;

        return $this;
    }

    public function getSlapperName(): string
    {
        return $this->slapperName;
    }

    /**
     * @return array{
     *     world: string,
     *     x: float,
     *     y: float,
     *     z: float,
     *     slapper: string,
     *     block: string
     * }
     */
    public function toArray(): array
    {
        $stateData =
            GlobalBlockStateHandlers::getSerializer()
                ->serialize($this->block->getStateId());

        $binary =
            (new LittleEndianNbtSerializer())
                ->write(
                    new TreeRoot(
                        $stateData->toNbt()
                    )
                );

        return [
            'world' =>
                $this->getWorld()->getFolderName(),

            'x' => $this->position->x,
            'y' => $this->position->y,
            'z' => $this->position->z,

            'slapper' => $this->slapperName,

            'block' => base64_encode($binary)
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function blockFromArray(
        array $data
    ): ?Block {
        if (
            !isset($data['block'])
            || !is_string($data['block'])
        ) {
            return null;
        }

        $binary = base64_decode(
            $data['block'],
            true
        );

        if ($binary === false) {
            return null;
        }

        try {
            $root =
                (new LittleEndianNbtSerializer())
                    ->read($binary);

            $stateData =
                BlockStateData::fromNbt(
                    $root->mustGetCompoundTag()
                );

            $stateId =
                GlobalBlockStateHandlers::getDeserializer()
                    ->deserialize(
                        $stateData
                    );

            return RuntimeBlockStateRegistry::getInstance()
                ->fromStateId(
                    $stateId
                );
        } catch (Throwable) {
            return null;
        }
    }
}