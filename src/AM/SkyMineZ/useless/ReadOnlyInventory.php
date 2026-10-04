<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;

final class ReadOnlyInventory
{
    /**
     * @var array<int, true>
     */
    private array $inventories = [];

    public function __construct(Plugin $plugin)
    {
        $pluginManager = $plugin->getServer()->getPluginManager();

        $pluginManager->registerEvent(
            InventoryTransactionEvent::class,
            function (InventoryTransactionEvent $event): void {
                foreach ($event->getTransaction()->getActions() as $action) {
                    if (
                        !$action instanceof SlotChangeAction
                    ) {
                        continue;
                    }

                    if (
                        $this->isReadOnly(
                            $action->getInventory()
                        )
                    ) {
                        $event->cancel();

                        return;
                    }
                }
            },
            EventPriority::HIGHEST,
            $plugin
        );

        $pluginManager->registerEvent(
            InventoryCloseEvent::class,
            function (InventoryCloseEvent $event): void {
                $this->remove(
                    $event->getInventory()
                );
            },
            EventPriority::MONITOR,
            $plugin
        );
    }

    public function add(
        Inventory $inventory
    ): void {
        $this->inventories[
        spl_object_id($inventory)
        ] = true;
    }

    public function remove(
        Inventory $inventory
    ): void {
        unset(
            $this->inventories[
            spl_object_id($inventory)
            ]
        );
    }

    public function isReadOnly(
        ?Inventory $inventory
    ): bool {
        if ($inventory === null) {
            return false;
        }

        return isset(
            $this->inventories[
            spl_object_id($inventory)
            ]
        );
    }

    public function open(
        Player $player,
        Inventory $inventory
    ): bool {
        $this->add($inventory);

        if (
            !$player->setCurrentWindow(
                $inventory
            )
        ) {
            $this->remove($inventory);

            return false;
        }

        return true;
    }

    public function clear(): void
    {
        $this->inventories = [];
    }
}