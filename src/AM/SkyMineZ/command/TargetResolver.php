<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Resolves the block a command should act on.
 *
 * Two sources are supported and both are used throughout the plugin:
 *
 *  - the block the player is looking at, for one-block commands such as
 *    `/crate create` or `/lb create`
 *  - the pos1/pos2 selection, for the cuboid commands (`/mine create`,
 *    `/outpost create`) where looking at two corners is not practical
 */
final class TargetResolver
{
    /** How far a player may be looking for a target block. */
    private const MAX_REACH = 6;

    private function __construct()
    {
    }

    /**
     * The block the player is looking at, or null when they are looking at
     * nothing within reach.
     */
    public static function lookedAtBlock(
        Player $player
    ): ?Block {
        return $player->getTargetBlock(
            self::MAX_REACH,
            self::TRANSPARENT
        );
    }

    public static function lookedAtPosition(
        Player $player
    ): ?Position {
        return self::lookedAtBlock(
            $player
        )?->getPosition();
    }

    /**
     * The block the player is standing on, which is the natural "here" target
     * for commands that build something at the player.
     */
    public static function blockBelow(
        Player $player
    ): Block {
        return $player->getWorld()->getBlock(
            $player->getPosition()
                ->down(1)
        );
    }

    /**
     * Falling back to the block under the player keeps every "click a block"
     * command usable while a player looks at the sky.
     */
    public static function blockOrBelow(
        Player $player
    ): Block {
        return self::lookedAtBlock($player)
            ?? self::blockBelow($player);
    }

    /**
     * Blocks a ray trace passes through: air and anything that does not stop
     * sight. Solids must be hit or the command would "find" the block behind a
     * wall.
     *
     * Empty would hit air immediately and return the air in front of the
     * player, breaking /crate create, /lb create and pos selection.
     *
     * @var array<int, true>
     */
    private const TRANSPARENT = [
        BlockTypeIds::AIR => true,
        BlockTypeIds::WATER => true,
        BlockTypeIds::LAVA => true,
        BlockTypeIds::GLASS => true,
        BlockTypeIds::GLASS_PANE => true,
        BlockTypeIds::LEAVES => true,
        BlockTypeIds::TALL_GRASS => true,
        BlockTypeIds::SNOW_LAYER => true,
    ];
}