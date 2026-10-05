<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

/**
 * Keeps mines from being griefed and shows their holograms to joining players.
 *
 * Inside a mine box only the refill itself may write blocks, so breaking and
 * placing are always refused there; players are meant to break the mine with
 * /mine reset, which restarts the timer as well.
 */
final class MineListener implements Listener
{
    public function __construct(
        private Main $main
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->main->getMineManager()->spawnTo(
            $event->getPlayer()
        );
    }

    public function onBreak(
        BlockBreakEvent $event
    ): void {
        if ($this->isMineBlock(
            $event->getBlock()
                ->getPosition()
        )) {
            $event->cancel();
        }
    }

    public function onPlace(
        BlockPlaceEvent $event
    ): void {
        /*
         * Placement uses a transaction rather than a single block, because
         * multi-block structures such as doors and beds touch more than one
         * position. Checking only the transaction's first position would let
         * players push the second half of a bed into a mine.
         */
        $player = $event->getPlayer();
        $world = $player->getWorld();

        foreach (
            $event->getTransaction()->getBlocks() as [$x, $y, $z]
        ) {
            if (
                $this->main
                    ->getMineManager()
                    ->getMineAt(
                        $world,
                        new Vector3(
                            $x,
                            $y,
                            $z
                        )
                    ) !== null
            ) {
                $event->cancel();

                return;
            }
        }
    }

    private function isMineBlock(
        Position $position
    ): bool {
        return $this->main
            ->getMineManager()
            ->getMineAt(
                $position->getWorld(),
                $position
            ) !== null;
    }
}