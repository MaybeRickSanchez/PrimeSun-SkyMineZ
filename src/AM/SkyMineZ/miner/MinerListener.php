<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

use AM\SkyMineZ\event\EventDispatcher;
use AM\SkyMineZ\event\MinerBlockMinedEvent;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\Combat;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
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

        if ($player->isCreative()) {
            return;
        }

        // Only blocks broken inside a configured mine count towards the
        // MINED stat. Everything else (lobby, wild, spawn) is ignored so the
        // sidebar and quests never inflate.
        if (
            $this->plugin->getMineManager()->getMineAt(
                $player->getWorld(),
                $event->getBlock()->getPosition()
            ) === null
        ) {
            return;
        }

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

        // Delta-only refresh so the sidebar shows the new MINED total
        // immediately instead of waiting for the next 20-tick pass.
        $this->plugin->getScoreHud()->updatePlayer($player);
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $victim = $event->getPlayer();

        $manager = $this->plugin->getMinerManager();
        $victimMiner = $manager->getOrLoad($victim->getName());

        $victimMiner->addDeath();
        $victimMiner->resetKillStreak();

        /*
         * The sidebar shows deaths and kill streaks, so refresh it right away
         * instead of waiting for the next pass. updatePlayer() is delta-only,
         * so this costs nothing when the board did not actually change.
         */
        $this->plugin->getScoreHud()->updatePlayer($victim);

        $killer = Combat::resolveKiller($victim);

        if ($killer !== null) {
            $this->registerKill($killer);
        }
    }

    private function registerKill(Player $player): void
    {
        $manager = $this->plugin->getMinerManager();
        $miner = $manager->getOrLoad($player->getName());

        $miner->addKill();

        $this->plugin->getScoreHud()->updatePlayer($player);
    }
}