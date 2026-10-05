<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

use AM\SkyMineZ\event\EventDispatcher;
use AM\SkyMineZ\event\MinerBlockMinedEvent;
use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

final class MinerListener implements Listener
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();

        if (!$manager->isLoaded($player->getName())) {
            $manager->load($player->getName());
        }
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();

        if ($manager->isLoaded($player->getName())) {
            $manager->saveAndUnload($player->getName());
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();

        /*
         * Skip the whole bookkeeping when nobody listens, so the common case
         * costs one static call instead of an object allocation.
         */
        if (MinerBlockMinedEvent::hasHandlers()) {
            $mined = new MinerBlockMinedEvent(
                $player->getName(),
                $player,
                $event->getBlock()
            );

            EventDispatcher::dispatch($mined);

            if ($mined->isCancelled()) {
                return;
            }

            $amount = $mined->getAmount();
        } else {
            $amount = 1;
        }

        $miner = $this->plugin->getMinerManager()->getOrLoad(
            $player->getName()
        );

        $miner->addMined($amount);
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $victim = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();
        $victimMiner = $manager->getOrLoad($victim->getName());

        $victimMiner->addDeath();
        $victimMiner->resetKillStreak();

        $damageCause = $victim->getLastDamageCause();

        if ($damageCause instanceof EntityDamageByChildEntityEvent) {
            $damager = $damageCause->getDamager();

            if ($damager instanceof Player) {
                $this->registerKill($damager);
            }

            return;
        }

        if ($damageCause instanceof EntityDamageByEntityEvent) {
            $damager = $damageCause->getDamager();

            if ($damager instanceof Player) {
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