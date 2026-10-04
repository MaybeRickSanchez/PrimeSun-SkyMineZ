<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class EconomyListener implements Listener
{
    public function __construct(
        private MoneyEconomy $money,
        private GoldEconomy $gold
    ) {
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $name = $event->getPlayer()->getName();
        $this->money->loadPlayer($name);
        $this->gold->loadPlayer($name);
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $name = $event->getPlayer()->getName();
        $this->money->unloadPlayer($name);
        $this->gold->unloadPlayer($name);
    }
}