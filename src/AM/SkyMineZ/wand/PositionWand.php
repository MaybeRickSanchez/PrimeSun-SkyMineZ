<?php

declare(strict_types=1);

namespace AM\SkyMineZ\wand;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * The Position Wand: a stick that selects cuboid corners instead of breaking
 * blocks.
 *
 * The selection mode lives on the item itself (an NBT tag), not in a manager,
 * so it survives relogs, deaths and server restarts without any extra state.
 * A normal click records pos1, a click while sneaking records pos2. The actual
 * coordinates live in {@link \AM\SkyMineZ\command\SelectionManager}, which is
 * what the mine/outpost commands already read — the wand is only another way
 * to fill it.
 */
final class PositionWand
{
    public const MODE_POS1 = 'pos1';
    public const MODE_POS2 = 'pos2';

    private const TAG_WAND = 'SkyMineZ_Wand';
    private const TAG_MODE = 'SkyMineZ_WandMode';

    private function __construct()
    {
    }

    public static function create(
        string $mode = self::MODE_POS1
    ): Item {
        $item = VanillaItems::STICK();

        $item->setCustomName("§dPosition Wand");
        $item->setLore([
            "§7Left-click a block: select §epos1",
            "§7Sneak + left-click: select §epos2",
            "§7Used by §d/mine create§7, §d/outpost create"
        ]);

        $tag = $item->getNamedTag();
        $tag->setByte(self::TAG_WAND, 1);

        $item->setNamedTag($tag);
        $item->setCount(1);

        return self::withMode($item, $mode);
    }

    public static function isWand(
        Item $item
    ): bool {
        return $item->getNamedTag()->getByte(self::TAG_WAND, 0) === 1;
    }

    public static function getMode(
        Item $item
    ): string {
        $mode = $item->getNamedTag()->getString(self::TAG_MODE, '');

        return $mode === self::MODE_POS2
            ? self::MODE_POS2
            : self::MODE_POS1;
    }

    public static function withMode(
        Item $item,
        string $mode
    ): Item {
        $item = clone $item;

        $tag = $item->getNamedTag();
        $tag->setString(
            self::TAG_MODE,
            $mode === self::MODE_POS2 ? self::MODE_POS2 : self::MODE_POS1
        );

        return $item->setNamedTag($tag);
    }
}