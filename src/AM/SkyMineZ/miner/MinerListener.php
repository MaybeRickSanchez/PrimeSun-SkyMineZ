<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\event\Listener;

final class MinerListener implements Listener
{
    public function __construct(
        private Plugin $plugin
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();

        if(!$manager->isLoaded($player->getName())){
            $manager->load($player->getName());
        }
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();

        if($manager->isLoaded($player->getName())){
            $manager->saveAndUnload($player->getName());
        }
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $victim = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();
        $victimMiner = $manager->getOrLoad($victim->getName());

        $victimMiner->addDeath();
        $victimMiner->resetKillStreak();

        $damageCause = $victim->getLastDamageCause();

        if($damageCause instanceof EntityDamageByEntityEvent){
            $damager = $damageCause->getDamager();

            if($damager instanceof Player){
                $this->registerKill($damager);
                return;
            }
        }

        if($damageCause instanceof EntityDamageByChildEntityEvent){
            $damager = $damageCause->getDamager();

            if($damager instanceof Player){
                $this->registerKill($damager);
            }
        }
    }

    private function registerKill(Player $player): void
    {
        $manager = $this->plugin->getMinerManager();
        $miner = $manager->getOrLoad($player->getName());

        $miner->addKill();
    }
}