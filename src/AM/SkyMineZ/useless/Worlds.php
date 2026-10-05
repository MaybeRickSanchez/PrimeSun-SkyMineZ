<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\world\World;
use pocketmine\world\WorldManager;

/**
 * World resolution shared by every manager that restores positioned data from
 * disk: look the world up by name, and load it when it exists on disk but is
 * not loaded yet.
 */
final class Worlds
{
    private function __construct()
    {
    }

    public static function resolve(
        WorldManager $worldManager,
        string $name
    ): ?World {
        $world = $worldManager->getWorldByName($name);

        if ($world !== null) {
            return $world;
        }

        if ($worldManager->isWorldGenerated($name)) {
            $worldManager->loadWorld($name);

            return $worldManager->getWorldByName($name);
        }

        return null;
    }
}