<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use AM\SkyMineZ\Main;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;

final class LagMaker implements Listener
{
    private const CLEANUP_INTERVAL = 20 * 60 * 30;

    private const ITEMS_PER_TICK = 100;

    private const MAX_DROPS_PER_PLAYER = 15;

    private const STACK_RADIUS = 1.5;

    /**
     * @var array<int, ItemEntity>
     */
    private array $queue = [];

    private bool $cleaning = false;

    private int $ticksUntilCleanup = self::CLEANUP_INTERVAL;

    private int $currentTick = 0;

    /**
     * @var array<string, int>
     */
    private array $dropCounts = [];

    /**
     * @var array<int, string>
     */
    private array $trackedItems = [];

    /**
     * @var array<int, array{
     *     player: string,
     *     itemName: string,
     *     world: World,
     *     x: float,
     *     y: float,
     *     z: float,
     *     expiresAt: int
     * }>
     */
    private array $pendingDrops = [];

    public function __construct(
        private Main $plugin
    ) {
        $this->plugin->getServer()
            ->getPluginManager()
            ->registerEvents($this, $this->plugin);

        $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(
                function (): void {
                    $this->tick();
                }
            ),
            1
        );
    }

    private function tick(): void
    {
        ++$this->currentTick;

        $this->cleanupPendingDrops();

        if ($this->cleaning) {
            $this->processBatch();

            return;
        }

        --$this->ticksUntilCleanup;

        if ($this->ticksUntilCleanup > 0) {
            return;
        }

        $this->ticksUntilCleanup = self::CLEANUP_INTERVAL;

        $this->startCleanup();
    }

    public function onPlayerDrop(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if ($item->isNull()) {
            return;
        }

        $playerName = strtolower(
            $player->getName()
        );

        $currentDrops =
            ($this->dropCounts[$playerName] ?? 0)
            + $this->getPendingDropCount($playerName);

        if ($currentDrops >= self::MAX_DROPS_PER_PLAYER) {
            if (!$this->canStackNearby(
                $player->getWorld(),
                $player->getPosition()->x,
                $player->getPosition()->y,
                $player->getPosition()->z,
                $item
            )) {
                $event->cancel();

                return;
            }
        }

        $location = $player->getLocation();

        $this->pendingDrops[] = [
            'player' => $playerName,
            'itemName' => $item->getName(),
            'world' => $player->getWorld(),
            'x' => $location->x,
            'y' => $location->y,
            'z' => $location->z,
            'expiresAt' => $this->currentTick + 10
        ];
    }

    public function onItemSpawn(ItemSpawnEvent $event): void
    {
        $entity = $event->getEntity();

        if (!$entity instanceof ItemEntity) {
            return;
        }

        $owner = strtolower(
            $entity->getThrower()
        );

        if ($owner === '') {
            $owner = $this->findPendingOwner($entity);

            if ($owner !== null) {
                $entity->setThrower($owner);
            }
        }

        if ($owner === '') {
            return;
        }

        $entityId = spl_object_id($entity);

        if (!isset($this->trackedItems[$entityId])) {
            $this->trackedItems[$entityId] = $owner;

            ++$this->dropCounts[$owner];
        }

        $this->stackNearby($entity);
    }

    public function onEntityDespawn(EntityDespawnEvent $event): void
    {
        $entity = $event->getEntity();

        if (!$entity instanceof ItemEntity) {
            return;
        }

        $entityId = spl_object_id($entity);

        $owner = $this->trackedItems[$entityId] ?? null;

        if ($owner === null) {
            return;
        }

        unset(
            $this->trackedItems[$entityId]
        );

        $this->decreaseDropCount($owner);
    }

    private function stackNearby(ItemEntity $source): void
    {
        if (
            $source->isClosed()
            || $source->isFlaggedForDespawn()
        ) {
            return;
        }

        $sourceItem = $source->getItem();

        if ($sourceItem->isNull()) {
            return;
        }

        $position = $source->getPosition();

        $box = new AxisAlignedBB(
            $position->x - self::STACK_RADIUS,
            $position->y - self::STACK_RADIUS,
            $position->z - self::STACK_RADIUS,
            $position->x + self::STACK_RADIUS,
            $position->y + self::STACK_RADIUS,
            $position->z + self::STACK_RADIUS
        );

        foreach (
            $source->getWorld()->getNearbyEntities(
                $box,
                $source
            ) as $nearby
        ) {
            if (!$nearby instanceof ItemEntity) {
                continue;
            }

            if (
                $nearby->isClosed()
                || $nearby->isFlaggedForDespawn()
            ) {
                continue;
            }

            $targetItem = $nearby->getItem();

            if (
                $targetItem->getName()
                !== $sourceItem->getName()
            ) {
                continue;
            }

            if (
                !$source->isMergeable($nearby)
            ) {
                continue;
            }

            if (
                !$source->tryMergeInto($nearby)
            ) {
                continue;
            }

            break;
        }
    }

    private function canStackNearby(
        World $world,
        float $x,
        float $y,
        float $z,
        Item $item
    ): bool {
        $box = new AxisAlignedBB(
            $x - self::STACK_RADIUS,
            $y - self::STACK_RADIUS,
            $z - self::STACK_RADIUS,
            $x + self::STACK_RADIUS,
            $y + self::STACK_RADIUS,
            $z + self::STACK_RADIUS
        );

        foreach (
            $world->getNearbyEntities($box) as $entity
        ) {
            if (!$entity instanceof ItemEntity) {
                continue;
            }

            if (
                $entity->isClosed()
                || $entity->isFlaggedForDespawn()
            ) {
                continue;
            }

            $targetItem = $entity->getItem();

            if (
                $targetItem->getName()
                !== $item->getName()
            ) {
                continue;
            }

            if (
                $targetItem->getCount()
                + $item->getCount()
                > $targetItem->getMaxStackSize()
            ) {
                continue;
            }

            if (
                !$targetItem->canStackWith($item)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function findPendingOwner(
        ItemEntity $entity
    ): ?string {
        $position = $entity->getPosition();

        $bestKey = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($this->pendingDrops as $key => $pending) {
            if (
                $pending['world']
                !== $entity->getWorld()
            ) {
                continue;
            }

            if (
                $pending['itemName']
                !== $entity->getItem()->getName()
            ) {
                continue;
            }

            if (
                $pending['expiresAt']
                < $this->currentTick
            ) {
                continue;
            }

            $dx = $position->x - $pending['x'];
            $dy = $position->y - $pending['y'];
            $dz = $position->z - $pending['z'];

            $distance = (
                $dx * $dx
                + $dy * $dy
                + $dz * $dz
            );

            if ($distance > 9.0) {
                continue;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestKey = $key;
            }
        }

        if ($bestKey === null) {
            return null;
        }

        $owner = $this->pendingDrops[$bestKey]['player'];

        unset(
            $this->pendingDrops[$bestKey]
        );

        return $owner;
    }

    private function getPendingDropCount(
        string $playerName
    ): int {
        $count = 0;

        foreach ($this->pendingDrops as $pending) {
            if (
                $pending['player']
                === $playerName
                && $pending['expiresAt']
                >= $this->currentTick
            ) {
                ++$count;
            }
        }

        return $count;
    }

    private function cleanupPendingDrops(): void
    {
        foreach ($this->pendingDrops as $key => $pending) {
            if (
                $pending['expiresAt']
                < $this->currentTick
            ) {
                unset(
                    $this->pendingDrops[$key]
                );
            }
        }
    }

    private function decreaseDropCount(
        string $playerName
    ): void {
        if (!isset($this->dropCounts[$playerName])) {
            return;
        }

        --$this->dropCounts[$playerName];

        if ($this->dropCounts[$playerName] <= 0) {
            unset(
                $this->dropCounts[$playerName]
            );
        }
    }

    private function startCleanup(): void
    {
        if ($this->cleaning) {
            return;
        }

        $this->queue = [];

        foreach (
            $this->plugin
                ->getServer()
                ->getWorldManager()
                ->getWorlds()
            as $world
        ) {
            $this->collectItems($world);
        }

        if ($this->queue === []) {
            return;
        }

        $this->cleaning = true;
    }

    private function collectItems(
        World $world
    ): void {
        foreach ($world->getEntities() as $entity) {
            if (!$entity instanceof ItemEntity) {
                continue;
            }

            if ($entity->isClosed()) {
                continue;
            }

            if ($entity->isFlaggedForDespawn()) {
                continue;
            }

            $this->queue[] = $entity;
        }
    }

    private function processBatch(): void
    {
        $processed = 0;

        while (
            $this->queue !== []
            && $processed < self::ITEMS_PER_TICK
        ) {
            $entity = array_pop(
                $this->queue
            );

            if ($entity === null) {
                break;
            }

            if (!$entity->isClosed()) {
                $entity->flagForDespawn();
            }

            ++$processed;
        }

        if ($this->queue === []) {
            $this->cleaning = false;
        }
    }
}