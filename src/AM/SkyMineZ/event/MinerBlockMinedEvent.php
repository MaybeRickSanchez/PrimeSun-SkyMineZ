<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use pocketmine\block\Block;
use pocketmine\player\Player;

/**
 * Raised after a player successfully broke a block, right before the block is
 * counted towards the "MINED" stat. Cancelling it keeps the stat untouched,
 * which is how a plugin can exclude blocks it considers worthless.
 */
final class MinerBlockMinedEvent extends CancellableSkyMineEvent
{
    private int $amount = 1;

    public function __construct(
        private string $playerName,
        private Player $player,
        private Block $block
    ) {
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getBlock(): Block
    {
        return $this->block;
    }

    /**
     * How many blocks this break should count as, useful for blocks with a
     * "value" different from one. Always at least 1.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = max(
            1,
            $amount
        );
    }
}