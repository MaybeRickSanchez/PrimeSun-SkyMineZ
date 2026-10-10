<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;

/**
 * Virtual chest window that can actually be shown to a player.
 *
 * PocketMine-MP 5.44+ (InventoryManager::onCurrentWindowChange) only knows
 * how to open BlockInventory windows via ContainerOpenPacket. A plain
 * SimpleInventory throws LogicException("Unsupported inventory type").
 *
 * This subclasses SimpleInventory (so all existing count/take/give helpers
 * keep working) and tags it with a BlockInventory holder. The holder is the
 * opener's current block position at open time (see VirtualWindow): a fake
 * chest block is sent to the client at that position first, otherwise the
 * client shows nothing while the server thinks a window is open and the
 * player gets soft-locked (can walk/chat but cannot open inventory).
 */
final class VirtualInventory extends SimpleInventory implements BlockInventory
{
    private Position $holder;

    public function __construct(
        Position $holder,
        int $size
    ) {
        $this->holder = $holder;
        parent::__construct($size);
    }

    public function getHolder(): Position
    {
        return $this->holder;
    }

    public function setHolder(Position $holder): void
    {
        $this->holder = $holder;
    }
}
