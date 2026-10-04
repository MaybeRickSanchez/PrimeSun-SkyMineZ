<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use AM\SkyMineZ\Main;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;

final class SlapperManager
{
    /**
     * @var array<string, Slapper>
     */
    private array $slappers = [];

    /**
     * @var array<int, Slapper>
     */
    private array $entities = [];

    /**
     * @var array<string, SlapperBlock>
     */
    private array $blocks = [];

    private Config $db;

    public function __construct(
        private Main $main
    ) {
        $this->db = new Config(
            $this->main->getDataFolder() .
            'slappers.json',
            Config::JSON
        );
    }

    public function load(): void
    {
        $this->despawnAll();

        $this->slappers = [];
        $this->entities = [];
        $this->blocks = [];

        foreach (
            $this->db->getAll()
            as $name => $data
        ) {
            if (
                !is_string($name) ||
                !is_array($data)
            ) {
                continue;
            }

            $slapper =
                $this->createSlapperFromArray(
                    $name,
                    $data
                );

            if ($slapper === null) {
                continue;
            }

            $this->slappers[$name] =
                $slapper;

            foreach (
                (array) (
                    $data['blocks'] ?? []
                ) as $blockName => $blockData
            ) {
                if (
                    !is_string($blockName) ||
                    !is_array($blockData)
                ) {
                    continue;
                }

                $block =
                    $this->createBlockFromArray(
                        $blockName,
                        $blockData
                    );

                if ($block === null) {
                    continue;
                }

                $this->blocks[$blockName] =
                    $block;
            }

            $this->spawn($name);

            foreach (
                $this->getBlocksForSlapper($name)
                as $block
            ) {
                $block->spawn();
            }
        }
    }

    private function createSlapperFromArray(
        string $name,
        array $data
    ): ?Slapper {
        if (
            !isset(
                $data['world'],
                $data['x'],
                $data['y'],
                $data['z'],
                $data['skin']
            )
        ) {
            return null;
        }

        $world =
            $this->resolveWorld(
                (string) $data['world']
            );

        if ($world === null) {
            return null;
        }

        $skin =
            $this->createSkinFromArray(
                $data['skin']
            );

        if ($skin === null) {
            return null;
        }

        $location =
            new \pocketmine\entity\Location(
                (float) $data['x'],
                (float) $data['y'],
                (float) $data['z'],
                $world,
                (float) ($data['yaw'] ?? 0),
                (float) ($data['pitch'] ?? 0)
            );

        $slapper = new Slapper(
            $name,
            $location,
            $skin
        );

        foreach (
            (array) (
                $data['commands'] ?? []
            ) as $command
        ) {
            if (is_string($command)) {
                $slapper->addCommand(
                    $command
                );
            }
        }

        foreach (
            (array) (
                $data['messages'] ?? []
            ) as $message
        ) {
            if (is_string($message)) {
                $slapper->addMessage(
                    $message
                );
            }
        }

        return $slapper;
    }

    private function createSkinFromArray(
        mixed $data
    ): ?\pocketmine\entity\Skin {
        if (!is_array($data)) {
            return null;
        }

        if (
            !isset(
                $data['id'],
                $data['data']
            )
        ) {
            return null;
        }

        $skinData =
            base64_decode(
                (string) $data['data'],
                true
            );

        if ($skinData === false) {
            return null;
        }

        $capeData =
            base64_decode(
                (string) (
                    $data['cape'] ?? ''
                ),
                true
            );

        if ($capeData === false) {
            $capeData = '';
        }

        $geometryData =
            base64_decode(
                (string) (
                    $data['geometryData'] ?? ''
                ),
                true
            );

        if ($geometryData === false) {
            $geometryData = '';
        }

        try {
            return new \pocketmine\entity\Skin(
                (string) $data['id'],
                $skinData,
                $capeData,
                (string) (
                    $data['geometryName'] ?? ''
                ),
                $geometryData
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function addSlapper(
        string $name,
        Position $position,
        Player|\pocketmine\entity\Skin $skin
    ): Slapper {
        if ($this->hasSlapper($name)) {
            throw new RuntimeException(
                "Slapper '{$name}' already exists."
            );
        }

        $skin =
            $skin instanceof Player
                ? $skin->getSkin()
                : $skin;

        $location =
            new \pocketmine\entity\Location(
                $position->x,
                $position->y,
                $position->z,
                $position->getWorld(),
                0,
                0
            );

        $slapper = new Slapper(
            $name,
            $location,
            $skin
        );

        $this->slappers[$name] =
            $slapper;

        $this->spawn($name);

        return $slapper;
    }

    public function spawn(
        string $name
    ): bool {
        $slapper =
            $this->getSlapper($name);

        if ($slapper === null) {
            return false;
        }

        $oldEntity =
            $slapper->getEntity();

        if ($oldEntity !== null) {
            unset(
                $this->entities[
                $oldEntity->getId()
                ]
            );
        }

        $slapper->spawn();

        $entity =
            $slapper->getEntity();

        if ($entity === null) {
            return false;
        }

        $this->entities[
        $entity->getId()
        ] = $slapper;

        return true;
    }

    public function despawn(
        string $name
    ): bool {
        $slapper =
            $this->getSlapper($name);

        if ($slapper === null) {
            return false;
        }

        $entity =
            $slapper->getEntity();

        if ($entity !== null) {
            unset(
                $this->entities[
                $entity->getId()
                ]
            );
        }

        $slapper->despawn();

        return true;
    }

    public function despawnAll(): void
    {
        foreach (
            $this->slappers as $slapper
        ) {
            $slapper->despawn();
        }

        foreach (
            $this->blocks as $block
        ) {
            $block->despawn();
        }

        $this->entities = [];
    }

    public function addBlock(
        string $name,
        Position $position,
        Block $block,
        string $slapperName
    ): SlapperBlock {
        if ($this->hasBlock($name)) {
            throw new RuntimeException(
                "Slapper block '{$name}' already exists."
            );
        }

        if (
            !$this->hasSlapper(
                $slapperName
            )
        ) {
            throw new RuntimeException(
                "Slapper '{$slapperName}' does not exist."
            );
        }

        $slapperBlock =
            new SlapperBlock(
                $name,
                $position,
                $block,
                $slapperName
            );

        $this->blocks[$name] =
            $slapperBlock;

        $slapperBlock->spawn();

        return $slapperBlock;
    }

    public function getSlapper(
        string $name
    ): ?Slapper {
        return $this->slappers[$name] ?? null;
    }

    public function getByEntityId(
        int $entityId
    ): ?Slapper {
        return $this->entities[$entityId] ?? null;
    }

    public function getByEntity(
        Entity $entity
    ): ?Slapper {
        return $this->getByEntityId(
            $entity->getId()
        );
    }

    public function hasSlapper(
        string $name
    ): bool {
        return isset(
            $this->slappers[$name]
        );
    }

    /**
     * @return array<string, Slapper>
     */
    public function getSlappers(): array
    {
        return $this->slappers;
    }

    public function addBlockFromSlapper(
        string $name,
        Slapper $slapper,
        Position $position,
        Block $block
    ): SlapperBlock {
        return $this->addBlock(
            $name,
            $position,
            $block,
            $slapper->getName()
        );
    }

    public function getBlock(
        string $name
    ): ?SlapperBlock {
        return $this->blocks[$name] ?? null;
    }

    /**
     * @return array<string, SlapperBlock>
     */
    public function getSlapperBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return list<SlapperBlock>
     */
    public function getBlocksForSlapper(
        string $slapperName
    ): array {
        $result = [];

        foreach (
            $this->blocks as $block
        ) {
            if (
                $block->getSlapperName()
                === $slapperName
            ) {
                $result[] = $block;
            }
        }

        return $result;
    }

    public function getBlockAt(
        Vector3 $position
    ): ?SlapperBlock {
        $x =
            $position->getFloorX();

        $y =
            $position->getFloorY();

        $z =
            $position->getFloorZ();

        $world =
            $position instanceof Position
                ? $position->getWorld()
                : null;

        foreach (
            $this->blocks as $block
        ) {
            $blockPosition =
                $block->getPosition();

            if (
                $world !== null &&
                $blockPosition->getWorld()
                !== $world
            ) {
                continue;
            }

            if (
                $blockPosition->getFloorX()
                === $x &&
                $blockPosition->getFloorY()
                === $y &&
                $blockPosition->getFloorZ()
                === $z
            ) {
                return $block;
            }
        }

        return null;
    }

    public function hasBlock(
        string $name
    ): bool {
        return isset(
            $this->blocks[$name]
        );
    }

    public function removeSlapper(
        string $name
    ): bool {
        $slapper =
            $this->getSlapper($name);

        if ($slapper === null) {
            return false;
        }

        $entity =
            $slapper->getEntity();

        if ($entity !== null) {
            unset(
                $this->entities[
                $entity->getId()
                ]
            );
        }

        $slapper->despawn();

        unset(
            $this->slappers[$name]
        );

        foreach (
            $this->blocks as $blockName => $block
        ) {
            if (
                $block->getSlapperName()
                !== $name
            ) {
                continue;
            }

            $block->remove();

            unset(
                $this->blocks[$blockName]
            );
        }

        return true;
    }

    public function removeBlock(
        string $name
    ): bool {
        $block =
            $this->getBlock($name);

        if ($block === null) {
            return false;
        }

        $block->remove();

        unset(
            $this->blocks[$name]
        );

        return true;
    }

    public function save(
        string $name
    ): void {
        $slapper =
            $this->getSlapper($name);

        if ($slapper === null) {
            return;
        }

        $data =
            $slapper->toArray();

        $data['blocks'] = [];

        foreach (
            $this->getBlocksForSlapper($name)
            as $block
        ) {
            $data['blocks'][
            $block->getName()
            ] =
                $block->toArray();
        }

        $this->db->set(
            $name,
            $data
        );

        $this->db->save();
    }

    public function saveAll(): void
    {
        $this->db->setAll([]);

        foreach (
            $this->slappers as $slapper
        ) {
            $data =
                $slapper->toArray();

            $data['blocks'] = [];

            foreach (
                $this->getBlocksForSlapper(
                    $slapper->getName()
                ) as $block
            ) {
                $data['blocks'][
                $block->getName()
                ] =
                    $block->toArray();
            }

            $this->db->set(
                $slapper->getName(),
                $data
            );
        }

        $this->db->save();
    }

    public function getDatabase(): Config
    {
        return $this->db;
    }

    private function createBlockFromArray(
        string $name,
        array $data
    ): ?SlapperBlock {
        if (
            !isset(
                $data['world'],
                $data['x'],
                $data['y'],
                $data['z'],
                $data['slapper'],
                $data['block']
            )
        ) {
            return null;
        }

        $world =
            $this->resolveWorld(
                (string) $data['world']
            );

        if ($world === null) {
            return null;
        }

        $block =
            SlapperBlock::blockFromArray(
                $data
            );

        if ($block === null) {
            return null;
        }

        $position = new Position(
            (float) $data['x'],
            (float) $data['y'],
            (float) $data['z'],
            $world
        );

        return new SlapperBlock(
            $name,
            $position,
            $block,
            (string) $data['slapper']
        );
    }

    private function resolveWorld(
        string $world
    ): ?World {
        $worldManager =
            $this->main
                ->getServer()
                ->getWorldManager();

        $resolved =
            $worldManager->getWorldByName(
                $world
            );

        if ($resolved !== null) {
            return $resolved;
        }

        if (
            $worldManager->isWorldGenerated(
                $world
            )
        ) {
            $worldManager->loadWorld(
                $world
            );

            return $worldManager->getWorldByName(
                $world
            );
        }

        return null;
    }
}