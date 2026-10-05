<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

use pocketmine\utils\Config;

/**
 * The hard currency shown as "GOLD" on the sidebar, also used as the reward for
 * holding an outpost. Backed by plugin_data/gold_economy.json.
 */
final class GoldEconomy extends BaseEconomy
{
    protected string $type = 'gold';

    public function __construct(
        Config $database,
        int $defaultBalance = 0
    ) {
        parent::__construct(
            $database,
            $defaultBalance
        );
    }
}