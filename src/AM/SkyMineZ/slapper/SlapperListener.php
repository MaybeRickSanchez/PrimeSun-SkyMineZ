<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;

final class SlapperListener implements Listener
{
    public function __construct(
        private SlapperManager $manager
    ) {
    }

    public function onSlapperInteract(
        PlayerEntityInteractEvent $event
    ): void {
        $slapper =
            $this->manager->getByEntity(
                $event->getEntity()
            );

        if ($slapper === null) {
            return;
        }

        $event->cancel();

        $slapper->execute(
            $event->getPlayer()
        );
    }

    public function onSlapperDamage(
        EntityDamageByEntityEvent $event
    ): void {
        $slapper =
            $this->manager->getByEntity(
                $event->getEntity()
            );

        if ($slapper === null) {
            return;
        }

        $event->cancel();
    }

    public function onBlockInteract(
        PlayerInteractEvent $event
    ): void {
        if (
            $event->getAction() !==
            PlayerInteractEvent::RIGHT_CLICK_BLOCK
        ) {
            return;
        }

        $slapperBlock =
            $this->manager->getBlockAt(
                $event->getBlock()->getPosition()
            );

        if ($slapperBlock === null) {
            return;
        }

        $event->cancel();

        $slapper =
            $this->manager->getSlapper(
                $slapperBlock->getSlapperName()
            );

        if ($slapper === null) {
            return;
        }

        $slapper->execute(
            $event->getPlayer()
        );
    }

    public function onBlockBreak(
        BlockBreakEvent $event
    ): void {
        $slapperBlock =
            $this->manager->getBlockAt(
                $event->getBlock()->getPosition()
            );

        if ($slapperBlock === null) {
            return;
        }

        $event->cancel();
    }

    public function onJoin(
        PlayerJoinEvent $event
    ): void {
        $player =
            $event->getPlayer();

        foreach (
            $this->manager->getSlappers()
            as $slapper
        ) {
            if (
                $slapper
                    ->getLocation()
                    ->getWorld()
                !== $player->getWorld()
            ) {
                continue;
            }

            $entity =
                $slapper->getEntity();

            if ($entity !== null) {
                $entity->spawnTo(
                    $player
                );
            }
        }

        foreach (
            $this->manager->getSlapperBlocks()
            as $block
        ) {
            if (
                $block->getWorld()
                !== $player->getWorld()
            ) {
                continue;
            }

            $block->spawnText(
                $player
            );
        }
    }
}