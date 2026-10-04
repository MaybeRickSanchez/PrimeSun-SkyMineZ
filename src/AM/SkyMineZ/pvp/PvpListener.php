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

final class PvpListener implements Listener
{
    private PvpManager $pvpManager;

    public function __construct()
    {
        $this->pvpManager = Main::getInstance()
            ->getPvpManager();
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->pvpManager->loadPlayer(
            $event->getPlayer()->getName()
        );
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $playerName = $event->getPlayer()->getName();

        $this->pvpManager->savePlayer(
            $playerName
        );

        $this->pvpManager->unloadPlayer(
            $playerName
        );
    }

    public function onDamage(
        EntityDamageByEntityEvent $event
    ): void {
        $victim = $event->getEntity();

        if (!$victim instanceof Player) {
            return;
        }

        $attacker = $this->getAttacker(
            $event->getDamager()
        );

        if ($attacker === null) {
            return;
        }

        if (
            !$this->pvpManager->getPlayerState(
                $attacker->getName()
            )
        ) {
            $event->cancel();

            return;
        }

        if (
            !$this->pvpManager->getPlayerState(
                $victim->getName()
            )
        ) {
            $event->cancel();
        }
    }

    private function getAttacker(
        Entity $damager
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