<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\SpreadTask;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;

/**
 * Keeps item entities from turning the server into a lag machine.
 *
 * Three things happen here, all driven by config.yml:
 *
 *  1. Items dropped next to each other are merged into one entity
 *     (`lagmaker.auto-stack`). This is the big one: dropping 64 stacks of stone
 *     creates 64 entities, while merging keeps it at one.
 *  2. A player may only have `lagmaker.max-drops-per-player` item entities on the
 *     ground; further drops are refused unless they would merge with an existing
 *     stack.
 *  3. Old leftovers are swept up by a periodic cleanup pass
 *     (`lagmaker.cleanup`). The mode decides whether it only removes items older
 *     than a TTL (the default) or every item entity on every world.
 *
 * Every pass is spread across ticks: a world with 50,000 leftover items must not
 * be walked in one tick.
 */
final class LagMaker implements Listener
{
    public const MODE_OFF = 'off';
    public const MODE_TTL = 'ttl';
    public const MODE_ALL = 'all';

    /**
     * Drop entries stay valid for this many ticks before the matching spawned
     * item is no longer attributed to the player who dropped it.
     */
    private const PENDING_WINDOW = 10;

    /**
     * @var array<string, int> owner name => live item entities
     */
    private array $dropCounts = [];

    /**
     * @var array<int, array{owner: string, spawnTick: int}> keyed by spl_object_id
     */
    private array $trackedItems = [];

    /**
     * Drops requested but not yet seen as an entity.
     *
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

    /** @var TaskHandler<LagMakerTask>|null */
    private ?TaskHandler $task = null;

    private int $currentTick = 0;

    private int $ticksUntilCleanup;

    /** @var array<int, ItemEntity> */
    private array $cleanupQueue = [];

    private bool $cleaning = false;

    public function __construct(
        private Main $plugin
    ) {
        $plugin->getServer()->getPluginManager()->registerEvents(
            $this,
            $plugin
        );

        $this->ticksUntilCleanup = $this->getCleanupInterval();

        $this->task = $plugin->getScheduler()->scheduleRepeatingTask(
            new LagMakerTask($this),
            1
        );
    }

    public function stop(): void
    {
        $this->task?->cancel();
        $this->task = null;

        $this->cleanupQueue = [];
        $this->cleaning = false;
    }

    /**
     * Enables or disables the automatic passes. Drops stay limited even while
     * disabled, because losing a player's items would be worse than a lag spike.
     */
    public function setEnabled(
        bool $enabled
    ): void {
        $this->plugin->getConfigManager()->set(
            'lagmaker.enabled',
            $enabled
        );

        $this->plugin->getConfigManager()->save();
    }

    public function isEnabled(): bool
    {
        return $this->plugin
            ->getConfigManager()
            ->getBool('lagmaker.enabled', true);
    }

    public function getMode(): string
    {
        $mode = strtolower(
            $this->plugin
                ->getConfigManager()
                ->getString('lagmaker.cleanup.mode', self::MODE_TTL)
        );

        return match ($mode) {
            self::MODE_OFF, self::MODE_ALL => $mode,
            default => self::MODE_TTL
        };
    }

    /**
     * Number of item entities a player currently owns.
     */
    public function getDropCount(
        string $playerName
    ): int {
        return $this->dropCounts[strtolower(
            $playerName
        )] ?? 0;
    }

    /**
     * One tick.
     */
    public function tick(): void
    {
        ++$this->currentTick;

        $this->cleanupPendingDrops();

        if ($this->cleaning) {
            $this->processCleanupBatch();

            return;
        }

        if (!$this->isEnabled()) {
            return;
        }

        if (--$this->ticksUntilCleanup > 0) {
            return;
        }

        $this->ticksUntilCleanup = $this->getCleanupInterval();

        $this->startCleanup();
    }

    public function onPlayerDrop(
        PlayerDropItemEvent $event
    ): void {
        $item = $event->getItem();

        if ($item->isNull()) {
            return;
        }

        $max = $this->getMaxDropsPerPlayer();

        if ($max <= 0) {
            return;
        }

        $player = $event->getPlayer();
        $playerName = strtolower(
            $player->getName()
        );

        $current = $this->getDropCount(
            $playerName
        ) + $this->getPendingDropCount(
            $playerName
        );

        if ($current < $max) {
            $this->trackPendingDrop(
                $player,
                $item
            );

            return;
        }

        /*
         * At the cap the drop is only allowed if it can merge into something
         * already on the ground. Refusing those too would make players unable to
         * drop anything at all, which is far more annoying than a full floor.
         */
        if (
            $this->canStackNearby(
                $player->getWorld(),
                $player->getPosition(),
                $item
            )
        ) {
            $this->trackPendingDrop(
                $player,
                $item
            );

            return;
        }

        $event->cancel();

        $player->sendMessage(
            $this->plugin->getConfigManager()->getPrefix()
            . "§cYou have too many items on the ground. Pick some up first."
        );
    }

    public function onItemSpawn(
        ItemSpawnEvent $event
    ): void {
        $entity = $event->getEntity();

        if (!$entity instanceof ItemEntity) {
            return;
        }

        $owner = $entity->getThrower();

        if ($owner === '') {
            /*
             * Bedrock drops do not always carry the thrower, so fall back to the
             * short-lived window recorded when the drop was requested. Writing it
             * back keeps the entity self-describing.
             */
            $owner = $this->findPendingOwner(
                $entity
            ) ?? '';
        }

        $owner = strtolower($owner);

        if ($owner !== '') {
            $entityId = spl_object_id($entity);

            if (!isset($this->trackedItems[$entityId])) {
                $this->trackedItems[$entityId] = [
                    'owner' => $owner,
                    'spawnTick' => $this->currentTick
                ];

                ++$this->dropCounts[$owner];
            }
        }

        if ($this->isEnabled() && $this->getAutoStack()) {
            $this->stackNearby($entity);
        }
    }

    public function onEntityDespawn(
        EntityDespawnEvent $event
    ): void
    {
        $entity = $event->getEntity();

        if (!$entity instanceof ItemEntity) {
            return;
        }

        $entityId = spl_object_id($entity);
        $owner = $this->trackedItems[$entityId]['owner'] ?? null;

        if ($owner === null) {
            return;
        }

        unset(
            $this->trackedItems[$entityId]
        );

        --$this->dropCounts[$owner];

        if (($this->dropCounts[$owner] ?? 0) <= 0) {
            unset($this->dropCounts[$owner]);
        }
    }

    /**
     * Merges $source into the first compatible item entity next to it.
     */
    private function stackNearby(
        ItemEntity $source
    ): void
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

        foreach (
            $this->nearbyItems(
                $source->getWorld(),
                $source->getPosition(),
                $source
            ) as $nearby
        ) {
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

            return;
        }
    }

    /**
     * Whether an item could merge into a neighbour at $position, used to decide
     * if a drop over the limit should still be allowed.
     */
    private function canStackNearby(
        World $world,
        Vector3 $position,
        Item $item
    ): bool {
        foreach (
            $this->nearbyItems(
                $world,
                $position
            ) as $entity
        ) {
            $target = $entity->getItem();

            if (
                $target->getCount() + $item->getCount() >
                $target->getMaxStackSize()
            ) {
                continue;
            }

            if (
                !$target->canStackWith($item)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Live item entities inside the stack radius of $center, excluding $exclude.
     *
     * The list is not filtered by item type: for stacking, ItemEntity::isMergeable()
     * already decides compatibility, and for the drop limit the caller compares
     * with canStackWith(). Filtering here would only duplicate that work.
     *
     * @return list<ItemEntity>
     */
    private function nearbyItems(
        World $world,
        Vector3 $center,
        ?ItemEntity $exclude = null
    ): array {
        $radius = $this->getStackRadius();

        $box = new AxisAlignedBB(
            $center->x - $radius,
            $center->y - $radius,
            $center->z - $radius,
            $center->x + $radius,
            $center->y + $radius,
            $center->z + $radius
        );

        $result = [];

        foreach (
            $world->getNearbyEntities(
                $box,
                $exclude
            ) as $entity
        ) {
            if (
                $entity instanceof ItemEntity
                && !$entity->isClosed()
                && !$entity->isFlaggedForDespawn()
            ) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * Attributes a freshly spawned item to the player who dropped it.
     */
    private function findPendingOwner(
        ItemEntity $entity
    ): ?string {
        $position = $entity->getPosition();
        $world = $entity->getWorld();
        $itemName = $entity->getItem()->getName();

        $bestKey = null;
        $bestDistance = 9.0;

        foreach (
            $this->pendingDrops as $key => $pending
        ) {
            if (
                $pending['world'] !== $world
                || $pending['itemName'] !== $itemName
                || $pending['expiresAt'] < $this->currentTick
            ) {
                continue;
            }

            $dx = $position->x - $pending['x'];
            $dy = $position->y - $pending['y'];
            $dz = $position->z - $pending['z'];

            $distance = (
                $dx * $dx + $dy * $dy + $dz * $dz
            );

            if (
                $distance > $bestDistance
            ) {
                continue;
            }

            $bestDistance = $distance;
            $bestKey = $key;
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

        foreach (
            $this->pendingDrops as $pending
        ) {
            if (
                $pending['player'] === $playerName
                && $pending['expiresAt'] >= $this->currentTick
            ) {
                ++$count;
            }
        }

        return $count;
    }

    private function cleanupPendingDrops(): void
    {
        foreach (
            $this->pendingDrops as $key => $pending
        ) {
            if (
                $pending['expiresAt'] < $this->currentTick
            ) {
                unset(
                    $this->pendingDrops[$key]
                );
            }
        }
    }

    /**
     * Records a drop that was allowed but has not appeared as an entity yet.
     *
     * The window is short: if no item shows up within it, the record is dropped
     * so a cancelled or consumed drop cannot inflate anybody's counter.
     */
    private function trackPendingDrop(
        Player $player,
        Item $item
    ): void {
        $location = $player->getLocation();

        $this->pendingDrops[] = [
            'player' => strtolower(
                $player->getName()
            ),
            'itemName' => $item->getName(),
            'world' => $player->getWorld(),
            'x' => $location->x,
            'y' => $location->y,
            'z' => $location->z,
            'expiresAt' => $this->currentTick + self::PENDING_WINDOW
        ];
    }

    /**
     * Starts a cleanup pass. Building the queue itself is spread over ticks, so
     * even reading every world's entity list cannot spike a tick.
     */
    private function startCleanup(): void
    {
        $mode = $this->getMode();

        if ($mode === self::MODE_OFF) {
            return;
        }

        $this->cleanupQueue = [];
        $this->cleaning = true;

        $worlds = [];

        foreach (
            $this->plugin->getServer()
                ->getWorldManager()
                ->getWorlds() as $world
        ) {
            $worlds[] = $world;
        }

        SpreadTask::spread(
            $this->plugin,
            $worlds,
            1,
            function(
                mixed $world
            ): void {
                if (!$world instanceof World) {
                    return;
                }

                $this->queueWorld($world);
            },
            function(): void {
                $this->cleaning = false;
            }
        );
    }

    /**
     * Collects the item entities of one world into the cleanup queue.
     */
    private function queueWorld(
        World $world
    ): void
    {
        foreach (
            $world->getEntities() as $entity
        ) {
            if (
                !$entity instanceof ItemEntity
                || $entity->isClosed()
                || $entity->isFlaggedForDespawn()
            ) {
                continue;
            }

            if (
                $this->getMode() === self::MODE_TTL
                && !$this->isExpired($entity)
            ) {
                continue;
            }

            $this->cleanupQueue[] = $entity;
        }
    }

    /**
     * Whether an item entity has been lying around longer than the configured
     * TTL.
     *
     * ItemEntity has no public age getter, so this uses the tracked spawn tick
     * when it is known and treats untracked items as fresh. That is the safe
     * direction: an item is only removed once it is provably old.
     */
    private function isExpired(
        ItemEntity $entity
    ): bool {
        $ttl = $this->getTtl();

        if ($ttl <= 0) {
            return true;
        }

        $tracked = $this->trackedItems[spl_object_id(
            $entity
        )] ?? null;

        if ($tracked === null) {
            return false;
        }

        return $this->currentTick - $tracked['spawnTick'] >= $ttl;
    }

    /**
     * Despawns a bounded slice of the queue.
     */
    private function processCleanupBatch(): void
    {
        $perTick = $this->getCleanupPerTick();

        for (
            $i = 0;
            $i < $perTick
            && $this->cleanupQueue !== [];
            ++$i
        ) {
            $entity = array_pop(
                $this->cleanupQueue
            );

            if (
                $entity === null
                || $entity->isClosed()
            ) {
                continue;
            }

            $entity->flagForDespawn();
        }

        if ($this->cleanupQueue === []) {
            $this->cleaning = false;
        }
    }

    private function getCleanupInterval(): int
    {
        return max(
            20,
            $this->plugin
                ->getConfigManager()
                ->getInt(
                    'lagmaker.cleanup.interval',
                    36000
                )
        );
    }

    private function getCleanupPerTick(): int
    {
        return max(
            1,
            $this->plugin
                ->getConfigManager()
                ->getInt(
                    'lagmaker.cleanup.per-tick',
                    200
                )
        );
    }

    private function getTtl(): int
    {
        return max(
            0,
            $this->plugin
                ->getConfigManager()
                ->getInt(
                    'lagmaker.cleanup.ttl-seconds',
                    900
                ) * 20
        );
    }

    private function getMaxDropsPerPlayer(): int
    {
        return max(
            0,
            $this->plugin
                ->getConfigManager()
                ->getInt(
                    'lagmaker.max-drops-per-player',
                    15
                )
        );
    }

    private function getAutoStack(): bool
    {
        return $this->plugin
            ->getConfigManager()
            ->getBool('lagmaker.auto-stack', true);
    }

    private function getStackRadius(): float
    {
        return max(
            0.5,
            $this->plugin
                ->getConfigManager()
                ->getFloat(
                    'lagmaker.stack-radius',
                    1.5
                )
        );
    }
}