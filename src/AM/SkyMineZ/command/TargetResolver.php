<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Resolves the block/position a command should act on.
 *
 * The canonical source is the player's middle (eye) position: every
 * create/move/spawn command uses it, so commands work the same whether the
 * player looks at the sky, the ground, or a wall. Cuboid corners keep coming
 * from the pos1/pos2 selection (chat commands or the Position Wand), which is
 * untouched.
 *
 * The looked-at helpers below are retained for API compatibility (external
 * plugins may call them) but nothing inside this plugin resolves positions
 * through the line of sight anymore.
 */
final class TargetResolver
{
    /** How far a player may be looking for a target block. */
    private const MAX_REACH = 6;

    private function __construct()
    {
    }

    /**
     * The player's middle position (eye height): the single canonical "here"
     * for every create/move/spawn command in the plugin.
     */
    public static function playerMiddle(
        Player $player
    ): Position {
        $eye = $player->getEyePos();

        return new Position(
            $eye->x,
            $eye->y,
            $eye->z,
            $player->getWorld()
        );
    }

    /**
     * The block the player is looking at, or null when they are looking at
     * nothing within reach.
     *
     * Kept for API compatibility; internal commands use playerMiddle().
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
     * Note: there is no generic BlockTypeIds::LEAVES — every leaf variant has
     * its own id, so all of them are listed explicitly.
     *
     * @var array<int, true>
     */
    private const TRANSPARENT = [
        BlockTypeIds::AIR => true,
        BlockTypeIds::WATER => true,
        BlockTypeIds::LAVA => true,
        BlockTypeIds::GLASS => true,
        BlockTypeIds::GLASS_PANE => true,
        BlockTypeIds::ACACIA_LEAVES => true,
        BlockTypeIds::BIRCH_LEAVES => true,
        BlockTypeIds::DARK_OAK_LEAVES => true,
        BlockTypeIds::JUNGLE_LEAVES => true,
        BlockTypeIds::OAK_LEAVES => true,
        BlockTypeIds::SPRUCE_LEAVES => true,
        BlockTypeIds::MANGROVE_LEAVES => true,
        BlockTypeIds::AZALEA_LEAVES => true,
        BlockTypeIds::FLOWERING_AZALEA_LEAVES => true,
        BlockTypeIds::CHERRY_LEAVES => true,
        BlockTypeIds::PALE_OAK_LEAVES => true,
        BlockTypeIds::TALL_GRASS => true,
        BlockTypeIds::SNOW_LAYER => true,
    ];
}