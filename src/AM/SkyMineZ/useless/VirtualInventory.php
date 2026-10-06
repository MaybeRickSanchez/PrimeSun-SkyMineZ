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
 * SimpleInventory throws LogicException("Unsupported inventory type") and
 * crashes the server thread while handling the form/inventory packet.
 *
 * This subclasses SimpleInventory (so all existing count/take/give helpers
 * keep working) and tags it with a BlockInventory holder. The holder is the
 * opener's position at open time; no real chest block is required, the
 * position is only echoed back in the ContainerOpen packet.
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
}
