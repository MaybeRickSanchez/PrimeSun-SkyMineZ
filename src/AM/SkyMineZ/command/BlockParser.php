<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use ReflectionClass;
use ReflectionMethod;
use function array_map;
use function strtolower;

/**
 * Resolves a block name typed by a player into a {@link Block} instance.
 *
 * PocketMine-MP 5.44 has no block name parser, but `VanillaBlocks` exposes one
 * static method per block whose name is exactly the block's identifier
 * (COBBLESTONE, DIAMOND_ORE, ...). That mapping is read once by reflection and
 * cached, which is both exact and cheap: the lookup is a hash hit afterwards.
 */
final class BlockParser
{
    /** @var array<string, string>|null lower-case name => VanillaBlocks method */
    private static ?array $names = null;

    /**
     * @var array<string, Block>
     */
    private static array $cache = [];

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function suggestions(): array
    {
        $names = self::names();

        return array_map(
            static fn(string $name): string => $name,
            array_values(
                array_slice(
                    $names,
                    0,
                    50
                )
            )
        );
    }

    /**
     * Accepts "cobblestone", "COBBLESTONE", "minecraft:cobblestone" and
     * "diamond_ore".
     */
    public static function parse(
        string $name
    ): ?Block {
        $key = strtolower(
            ltrim(
                $name,
                ':'
            )
        );

        if (
            str_contains(
                $key,
                ':'
            )
        ) {
            $key = substr(
                $key,
                strpos(
                    $key,
                    ':'
                ) + 1
            );
        }

        if (isset(self::$cache[$key])) {
            return clone self::$cache[$key];
        }

        $names = self::names();
        $method = $names[$key] ?? null;

        if ($method === null) {
            return null;
        }

        $block = self::instantiate($method);

        if ($block === null) {
            return null;
        }

        /*
         * Cached once per name. Blocks are immutable value objects in PM5, so
         * handing out clones is enough; caching avoids repeating the reflection
         * call for every block a mine is built from.
         */
        self::$cache[$key] = $block;

        return clone $block;
    }

    /**
     * @return array<string, string>
     */
    private static function names(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $names = [];

        $class = new ReflectionClass(
            \pocketmine\block\VanillaBlocks::class
        );

        foreach (
            $class->getMethods(
                ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC
            ) as $method
        ) {
            if (
                $method->getNumberOfRequiredParameters() > 0
            ) {
                continue;
            }

            $names[strtolower(
                $method->getName()
            )] = $method->getName();
        }

        return self::$names = $names;
    }

    private static function instantiate(
        string $method
    ): ?Block {
        try {
            /** @var Block $block */
            $block = \pocketmine\block\VanillaBlocks::{$method}();

            return RuntimeBlockStateRegistry::getInstance()->fromStateId(
                $block->getStateId()
            );
        } catch (\Throwable) {
            return null;
        }
    }
}