<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

use pocketmine\utils\Config;

/**
 * The soft currency shown as "MONEY" on the sidebar. Backed by
 * plugin_data/money_economy.json.
 */
final class MoneyEconomy extends BaseEconomy
{
    protected string $type = 'money';

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