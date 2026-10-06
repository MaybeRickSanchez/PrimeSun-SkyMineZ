<?php

declare(strict_types=1);

namespace AM\SkyMineZ\pvp;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
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

        /*
         * Duel opponents may always fight, regardless of PvP toggles. This is
         * the compatibility hook between duels and the PvP system: accepting a
         * duel is explicit consent to this damage.
         */
        if (
            $this->main->getTeamManager()->areOpponents(
                $attacker->getName(),
                $victim->getName()
            )
        ) {
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
         *
         * The legacy `pvp.disabled-message` key still wins when set, so old
         * configs keep their custom text; otherwise the shared catalog is
         * used. Exactly one of the two is read — never both.
         */
        if (
            $manager->getState($attacker->getName())
        ) {
            $legacy = $config->getString('pvp.disabled-message', '');

            $message = $legacy !== ''
                ? str_replace(
                    '{player}',
                    $attacker->getName(),
                    $legacy
                )
                : Messages::get(
                    $this->main,
                    Messages::PVP_OFF,
                    ['player' => $attacker->getName()]
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