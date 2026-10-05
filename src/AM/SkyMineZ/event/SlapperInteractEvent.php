<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use AM\SkyMineZ\slapper\Slapper;
use pocketmine\player\Player;

/**
 * Raised when a player right-clicks a slapper, before its messages and commands
 * run. Cancelling it silences the slapper without breaking the interaction.
 */
final class SlapperInteractEvent extends CancellableSkyMineEvent
{
    public function __construct(
        private Slapper $slapper,
        private Player $player
    ) {
    }

    public function getSlapper(): Slapper
    {
        return $this->slapper;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }
}