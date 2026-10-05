<?php

declare(strict_types=1);

namespace AM\SkyMineZ\pvp;

use AM\SkyMineZ\Main;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * Blocks damage between players who have PvP disabled.
 *
 * Both parties must have PvP enabled, otherwise a player could switch it off and
 * still be attacked.
 */
final class PvpListener implements Listener
{
    private Main $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->main->getPvpManager()->loadPlayer(
            $event->getPlayer()->getName()
        );
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $this->main->getPvpManager()->unloadPlayer(
            $event->getPlayer()->getName()
        );
    }

    public function onDamage(
        EntityDamageByEntityEvent $event
    ): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();

        if (!$victim instanceof Player) {
            return;
        }

        $attacker = $this->resolveAttacker(
            $event->getDamager()
        );

        if ($attacker === null) {
            return;
        }

        $manager = $this->main->getPvpManager();

        if ($manager->canDamage(
            $attacker->getName(),
            $victim->getName()
        )) {
            return;
        }

        $event->cancel();

        $config = $this->main->getConfigManager();

        /*
         * Only nag the victim when the attacker is the one who turned PvP off.
         * Being hit by someone who is fine themselves is not confusing.
         */
        if (
            $manager->getState($attacker->getName())
        ) {
            $message = $config->message(
                'pvp.disabled-message',
                [
                    'player' => $attacker->getName()
                ]
            );

            if ($message !== '') {
                $victim->sendMessage(
                    $config->getPrefix() . $message
                );
            }
        }
    }

    /**
     * Resolves the player behind a damage source, following projectiles back to
     * their owner.
     */
    private function resolveAttacker(
        ?Entity $damager
    ): ?Player {
        if ($damager instanceof Player) {
            return $damager;
        }

        if ($damager instanceof Projectile) {
            $owner = $damager->getOwningEntity();

            if ($owner instanceof Player) {
                return $owner;
            }
        }

        return null;
    }
}