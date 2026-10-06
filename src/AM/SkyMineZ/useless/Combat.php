<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

/**
 * Shared combat attribution: who killed a player, if anyone did.
 *
 * Projectile kills resolve to the shooter first (child event), direct hits
 * second. Several systems need exactly this answer (miner kill stats, quest
 * kill progress), and each had its own copy.
 */
final class Combat
{
    private function __construct()
    {
    }

    public static function resolveKiller(
        Player $victim
    ): ?Player {
        $damageCause = $victim->getLastDamageCause();

        if ($damageCause instanceof EntityDamageByChildEntityEvent) {
            $damager = $damageCause->getDamager();

            return $damager instanceof Player ? $damager : null;
        }

        if ($damageCause instanceof EntityDamageByEntityEvent) {
            $damager = $damageCause->getDamager();

            return $damager instanceof Player ? $damager : null;
        }

        return null;
    }
}