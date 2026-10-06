<?php

declare(strict_types=1);

namespace AM\SkyMineZ\economy;

use AM\SkyMineZ\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * Keeps both currencies loaded for every online player and writes them back on
 * quit. The actual file write happens in onDisable, so quitting during a busy
 * minute costs one array assignment instead of a disk write.
 */
final class EconomyListener implements Listener
{
    /**
     * @var list<BaseEconomy>
     */
    private array $economies;

    public function __construct(Main $main)
    {
        $this->economies = [
            $main->getMoneyEconomy(),
            $main->getGoldEconomy()
        ];
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $name = $event->getPlayer()->getName();

        foreach (
            $this->economies as $economy
        ) {
            $economy->loadPlayer($name);
        }
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $name = $event->getPlayer()->getName();

        foreach (
            $this->economies as $economy
        ) {
            $economy->unloadPlayer($name);
        }
    }
}