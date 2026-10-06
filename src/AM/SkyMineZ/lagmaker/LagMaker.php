<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;

/**
 * Keeps item entities from turning the server into a lag machine.
 *
 * Three things happen here, all driven by config.yml:
 *
 *  1. Items dropped next to each other are merged into one entity
 *     (`lagmaker.auto-stack`).
 *  2. A player may only have `lagmaker.max-drops-per-player` item entities on
 *     the ground; further drops are refused unless they would merge.
 *  3. Old leftovers are swept up by a periodic cleanup pass
 *     (`lagmaker.cleanup`, modes off/ttl/all).
 *
 * The work is split so each class owns its own state: {@link DropTracker}
 * owns all ownership bookkeeping, {@link ItemSweeper} owns the cleanup pass,
 * {@link LagMakerSettings} is the per-tick config snapshot, and this class
 * only orchestrates events, the tick and the repeating task.
 *
 * Task ownership: the per-tick {@link LagMakerTask} is scheduled here and
 * cancelled in {@link stop()}. The sweeper's queue-building SpreadTask cancels
 * itself; `stop()` additionally freezes it via the sweeper. Nothing scheduled
 * here survives disable.
 */
final class LagMaker implements Listener
{
    public const MODE_OFF = 'off';
    public const MODE_TTL = 'ttl';
    public const MODE_ALL = 'all';

    private DropTracker $tracker;

    private ItemSweeper $sweeper;

    /** @var TaskHandler<LagMakerTask>|null */
    private ?TaskHandler $task = null;

    private int $currentTick = 0;

    private int $ticksUntilCleanup;

    /**
     * Per-tick snapshot of the tuning, rebuilt at most once per tick. Events
     * and the tick share it, so a drop-heavy tick pays one config read instead
     * of one per event. A mid-tick config change applies on the next tick.
     */
    private ?LagMakerSettings $settingsCache = null;

    private int $settingsCacheTick = -1;

    public function __construct(
        private Main $plugin
    ) {
        $plugin->getServer()->getPluginManager()->registerEvents(
            $this,
            $plugin
        );

        $this->tracker = new DropTracker();
        $this->sweeper = new ItemSweeper($plugin);

        $this->ticksUntilCleanup = LagMakerSettings::fromConfig(
            $plugin->getConfigManager()
        )->cleanupInterval;

        $this->task = $plugin->getScheduler()->scheduleRepeatingTask(
            new LagMakerTask($this),
            1
        );
    }

    public function stop(): void
    {
        $this->task?->cancel();
        $this->task = null;

        $this->sweeper->stop();
        $this->tracker->clear();
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
        return $this->settings()->mode;
    }

    /**
     * One tick's tuning. Rebuilt at most once per tick and shared by the tick
     * and every event inside it.
     */
    private function settings(): LagMakerSettings
    {
        if (
            $this->settingsCache === null
            || $this->settingsCacheTick !== $this->currentTick
        ) {
            $this->settingsCache = LagMakerSettings::fromConfig(
                $this->plugin->getConfigManager()
            );
            $this->settingsCacheTick = $this->currentTick;
        }

        return $this->settingsCache;
    }

    /**
     * One tick.
     *
     * Clear warnings (30/5/2/1s) ride on this same counter: no extra task is
     * scheduled, the countdown state machine just broadcasts when the
     * remaining whole seconds hit one of those marks.
     */
    public function tick(): void
    {
        ++$this->currentTick;

        $settings = $this->settings();

        $this->tracker->sweepExpired($this->currentTick);

        if ($this->sweeper->isCleaning()) {
            $this->sweeper->setPerTick($settings->cleanupPerTick);
            $this->sweeper->processBatch();

            return;
        }

        if (!$settings->enabled || $settings->cleanupDisabled()) {
            return;
        }

        if (--$this->ticksUntilCleanup > 0) {
            $this->maybeWarn($this->ticksUntilCleanup);

            return;
        }

        $this->ticksUntilCleanup = $settings->cleanupInterval;

        $this->broadcastClear($settings);
        $this->startCleanup($settings);
    }

    /**
     * Warns at exactly 30/5/2/1 seconds before the clear, synchronized with
     * the same counter that triggers it.
     */
    private function maybeWarn(int $ticksLeft): void
    {
        // Only on whole-second boundaries to broadcast once per mark.
        if ($ticksLeft % 20 !== 0) {
            return;
        }

        $seconds = (int) ($ticksLeft / 20);

        if ($seconds !== 30 && $seconds !== 5 && $seconds !== 2 && $seconds !== 1) {
            return;
        }

        $prefix = $this->plugin->getConfigManager()->getPrefix();

        $this->plugin->getServer()->broadcastMessage(
            $prefix . Messages::get(
                $this->plugin,
                Messages::LAG_WARN,
                ['seconds' => $seconds]
            )
        );
    }

    private function broadcastClear(LagMakerSettings $settings): void
    {
        $key = $settings->removeEverything()
            ? Messages::LAG_CLEAR_ALL
            : Messages::LAG_CLEAR_TTL;

        $this->plugin->getServer()->broadcastMessage(
            $this->plugin->getConfigManager()->getPrefix()
            . Messages::get(
                $this->plugin,
                $key,
                ['seconds' => (int) ($settings->ttlTicks / 20)]
            )
        );
    }

    public function onPlayerDrop(
        PlayerDropItemEvent $event
    ): void {
        if ($event->isCancelled()) {
            return;
        }

        $item = $event->getItem();

        if ($item->isNull()) {
            return;
        }

        $settings = $this->settings();

        if ($settings->maxDropsPerPlayer <= 0) {
            return;
        }

        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());

        $current = $this->tracker->getCount($playerName)
            + $this->tracker->pendingCount($playerName, $this->currentTick);

        if ($current < $settings->maxDropsPerPlayer) {
            $this->tracker->trackDrop($player, $item, $this->currentTick);

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
                $item,
                $settings->stackRadius
            )
        ) {
            $this->tracker->trackDrop($player, $item, $this->currentTick);

            return;
        }

        $event->cancel();

        $player->sendMessage(
            $this->plugin->getConfigManager()->getPrefix()
            . Messages::get($this->plugin, Messages::LAG_TOO_MANY)
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
             * short-lived window recorded when the drop was requested.
             */
            $owner = $this->tracker->resolveOwner($entity, $this->currentTick);
        }

        $this->tracker->noteSpawned(
            $entity,
            strtolower($owner),
            $this->currentTick
        );

        $settings = $this->settings();

        if ($settings->enabled && $settings->autoStack) {
            $this->stackNearby($entity, $settings->stackRadius);
        }
    }

    public function onEntityDespawn(
        EntityDespawnEvent $event
    ): void {
        $entity = $event->getEntity();

        if (!$entity instanceof ItemEntity) {
            return;
        }

        $this->tracker->noteDespawned($entity);
    }

    /**
     * A world going away must not leave broken references behind. Tracked
     * entities are deliberately left alone: the unload closes them, and those
     * close events settle the counters through the normal path. Only the
     * speculative state (pending drops, queued sweep entries) is purged.
     */
    public function onWorldUnload(
        WorldUnloadEvent $event
    ): void {
        $world = $event->getWorld();

        $this->tracker->purgeWorld($world);
        $this->sweeper->purgeWorld($world);
    }

    /**
     * Merges $source into the first compatible item entity next to it.
     */
    private function stackNearby(
        ItemEntity $source,
        float $radius
    ): void {
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
                $radius,
                $source
            ) as $nearby
        ) {
            if (!$source->isMergeable($nearby)) {
                continue;
            }

            if (!$source->tryMergeInto($nearby)) {
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
        Item $item,
        float $radius
    ): bool {
        foreach (
            $this->nearbyItems($world, $position, $radius) as $entity
        ) {
            $target = $entity->getItem();

            if (
                $target->getCount() + $item->getCount() >
                $target->getMaxStackSize()
            ) {
                continue;
            }

            if (!$target->canStackWith($item)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Live item entities inside the stack radius of $center, excluding $exclude.
     *
     * The list is not filtered by item type: for stacking,
     * ItemEntity::isMergeable() already decides compatibility, and for the drop
     * limit the caller compares with canStackWith(). Filtering here would only
     * duplicate that work.
     *
     * @return list<ItemEntity>
     */
    private function nearbyItems(
        World $world,
        Vector3 $center,
        float $radius,
        ?ItemEntity $exclude = null
    ): array {
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
            $world->getNearbyEntities($box, $exclude) as $entity
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
     * Starts a cleanup pass. Building the queue itself is spread over ticks, so
     * even reading every world's entity list cannot spike a tick.
     *
     * Age is evaluated live (current tick at queue time), not frozen at pass
     * start, so a long spread pass still expires items that aged while it was
     * being built.
     */
    private function startCleanup(
        LagMakerSettings $settings
    ): void {
        if ($settings->cleanupDisabled()) {
            return;
        }

        $worlds = [];

        foreach (
            $this->plugin->getServer()
                ->getWorldManager()
                ->getWorlds() as $world
        ) {
            $worlds[] = $world;
        }

        $removeEverything = $settings->removeEverything();
        $ttl = $settings->ttlTicks;

        $this->sweeper->setPerTick($settings->cleanupPerTick);

        $this->sweeper->begin(
            $worlds,
            function(ItemEntity $entity) use ($removeEverything, $ttl): bool {
                return $removeEverything
                    || $this->tracker->isExpired($entity, $ttl, $this->currentTick);
            }
        );
    }
}