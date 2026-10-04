<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerQuitEvent;

class OutpostListener implements Listener
{
    private OutpostManager $manager;

    public function __construct(OutpostManager $manager)
    {
        $this->manager = $manager;
    }

    public function onJump(PlayerJumpEvent $event): void
    {
        $this->manager->handleJump($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $this->manager->handleQuit($event->getPlayer());
    }
}