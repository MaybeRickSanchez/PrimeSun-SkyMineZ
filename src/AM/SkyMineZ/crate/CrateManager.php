<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\ReadOnlyInventory;
use JsonException;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;

final class CrateManager
{
    /**
     * @var array<string, Crate>
     */
    private array $crates = [];

    private ReadOnlyInventory $readOnlyInventory;

    public function __construct(
        private Main $main
    ) {
        $this->readOnlyInventory =
            new ReadOnlyInventory(
                $this->main
            );
    }

    public function load(): void
    {
        $this->crates = [];

        $db =
            $this->main->getCrateDB();

        foreach (
            $db->getAll()
            as $crateName => $crateData
        ) {
            if (
                !is_string($crateName) ||
                !is_array($crateData)
            ) {
                continue;
            }

            if (
                !isset(
                    $crateData['world'],
                    $crateData['x'],
                    $crateData['y'],
                    $crateData['z']
                )
            ) {
                continue;
            }

            $worldName =
                (string) $crateData['world'];

            $worldManager =
                $this->main
                    ->getServer()
                    ->getWorldManager();

            $world =
                $worldManager->getWorldByName(
                    $worldName
                );

            if ($world === null) {
                if (
                    $worldManager
                        ->isWorldGenerated(
                            $worldName
                        )
                ) {
                    $worldManager->loadWorld(
                        $worldName
                    );

                    $world =
                        $worldManager
                            ->getWorldByName(
                                $worldName
                            );
                }
            }

            if ($world === null) {
                continue;
            }

            $position = new Position(
                (float) $crateData['x'],
                (float) $crateData['y'],
                (float) $crateData['z'],
                $world
            );

            $crate = new Crate(
                $this->main,
                $this->readOnlyInventory,
                $crateName,
                $position
            );

            foreach (
                (array) (
                    $crateData['keys'] ?? []
                ) as $keyId
            ) {
                if (
                    is_string($keyId)
                ) {
                    $crate->addKey(
                        $keyId
                    );
                }
            }

            foreach (
                (array) (
                    $crateData['rewards'] ?? []
                ) as $rewardData
            ) {
                if (
                    !is_array($rewardData)
                ) {
                    continue;
                }

                $reward =
                    Reward::fromArray(
                        $rewardData
                    );

                if ($reward !== null) {
                    $crate->addRewardObject(
                        $reward
                    );
                }
            }

            $this->crates[
            $crateName
            ] = $crate;

            $crate->spawn();
            $crate->update();
        }
    }

    public function addCrate(
        string $name,
        array|Vector3 $position,
        string|World $world
    ): Crate {
        if ($this->hasCrate($name)) {
            throw new RuntimeException(
                "Crate '$name' already exists."
            );
        }

        $world =
            $this->resolveWorld($world);

        if (
            $position instanceof Vector3
        ) {
            $position = new Position(
                $position->x,
                $position->y,
                $position->z,
                $world
            );
        } else {
            $position = new Position(
                (float) $position['x'],
                (float) $position['y'],
                (float) $position['z'],
                $world
            );
        }

        $crate = new Crate(
            $this->main,
            $this->readOnlyInventory,
            $name,
            $position
        );

        $this->crates[$name] =
            $crate;

        $crate->spawn();

        return $crate;
    }

    public function getCrate(
        string $name
    ): ?Crate {
        return $this->crates[$name] ?? null;
    }

    public function hasCrate(
        string $name
    ): bool {
        return isset(
            $this->crates[$name]
        );
    }

    public function getCrateAt(
        Vector3 $position
    ): ?Crate {
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        $world =
            $position instanceof Position
                ? $position->getWorld()
                : null;

        foreach (
            $this->crates as $crate
        ) {
            $cratePosition =
                $crate->getPosition();

            if (
                $world !== null &&
                $cratePosition->getWorld()
                !== $world
            ) {
                continue;
            }

            if (
                $cratePosition->getFloorX()
                === $x &&
                $cratePosition->getFloorY()
                === $y &&
                $cratePosition->getFloorZ()
                === $z
            ) {
                return $crate;
            }
        }

        return null;
    }

    /**
     * @throws JsonException
     */
    public function removeCrate(
        string $name
    ): bool {
        $crate =
            $this->getCrate($name);

        if ($crate === null) {
            return false;
        }

        $position =
            $crate->getPosition();

        $world =
            $position->getWorld();

        $crate->despawn();

        if (
            $world->getBlock($position)
                ->hasSameTypeId(
                    VanillaBlocks::CHEST()
                )
        ) {
            $world->setBlock(
                $position,
                VanillaBlocks::AIR()
            );
        }

        unset(
            $this->crates[$name]
        );

        $db =
            $this->main->getCrateDB();

        if ($db->exists($name)) {
            $db->remove($name);
        }

        $db->save();

        return true;
    }

    /**
     * @throws JsonException
     */
    public function saveCrate(
        string $name
    ): void {
        $crate =
            $this->getCrate($name);

        if ($crate === null) {
            return;
        }

        $db =
            $this->main->getCrateDB();

        $db->set(
            $name,
            $crate->toArray()
        );

        $db->save();
    }

    /**
     * @throws JsonException
     */
    public function saveAll(): void
    {
        $db =
            $this->main->getCrateDB();

        foreach (
            $this->crates as $crate
        ) {
            $db->set(
                $crate->getName(),
                $crate->toArray()
            );
        }

        $db->save();
    }

    /**
     * @return array<string, Crate>
     */
    public function getCrates(): array
    {
        return $this->crates;
    }

    public function getMain(): Main
    {
        return $this->main;
    }

    private function resolveWorld(
        string|World $world
    ): World {
        if ($world instanceof World) {
            return $world;
        }

        $worldManager =
            $this->main
                ->getServer()
                ->getWorldManager();

        $resolvedWorld =
            $worldManager->getWorldByName(
                $world
            );

        if ($resolvedWorld !== null) {
            return $resolvedWorld;
        }

        if (
            $worldManager->isWorldGenerated(
                $world
            )
        ) {
            $worldManager->loadWorld(
                $world
            );

            $resolvedWorld =
                $worldManager->getWorldByName(
                    $world
                );

            if (
                $resolvedWorld !== null
            ) {
                return $resolvedWorld;
            }
        }

        throw new RuntimeException(
            "World '$world' could not be loaded."
        );
    }
}