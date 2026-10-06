<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * Owns every piece of LagMaker ownership bookkeeping in one place:
 *
 *  - live per-player item counts (`dropCounts`)
 *  - entity records keyed by spl_object_id (`tracked`)
 *  - drops seen as events but not yet as entities (`pending`)
 *
 * The invariant is simple: `dropCounts[name]` always equals the number of
 * tracked records owned by `name`. Every mutation goes through the methods
 * below so the two structures can never disagree — a disagreement used to
 * crash the server, because incrementing a missing counter emits a warning
 * and PocketMine escalates warnings to fatal errors.
 *
 * All transitions are idempotent: noting the same despawn twice, or a despawn
 * for an entity that was never tracked, is a safe no-op. That is what makes
 * drop-then-instantly-pick-up, merges, chunk unloads and world unloads safe in
 * any order.
 */
final class DropTracker
{
    /**
     * Drop entries stay valid for this many ticks before the matching spawned
     * item is no longer attributed to the player who dropped it.
     */
    private const PENDING_WINDOW = 10;

    /** @var array<string, int> owner name => live item entities */
    private array $dropCounts = [];

    /**
     * Entity records keyed by runtime entity ID (not spl_object_id: PHP reuses
     * object IDs after free, so a new entity could inherit a stale record).
     *
     * @var array<int, array{owner: string, spawnTick: int}>
     */
    private array $tracked = [];

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
    private array $pending = [];

    /**
     * Records a drop that was allowed but has not appeared as an entity yet.
     *
     * The window is short: if no item shows up within it, the record is dropped
     * so a cancelled or consumed drop cannot inflate anybody's counter.
     */
    public function trackDrop(
        Player $player,
        Item $item,
        int $now
    ): void {
        if ($item->isNull()) {
            return;
        }

        $location = $player->getLocation();

        $this->pending[] = [
            'player' => strtolower($player->getName()),
            'itemName' => $item->getName(),
            'world' => $player->getWorld(),
            'x' => $location->x,
            'y' => $location->y,
            'z' => $location->z,
            'expiresAt' => $now + self::PENDING_WINDOW
        ];
    }

    public function pendingCount(
        string $playerName,
        int $now
    ): int {
        $count = 0;

        foreach ($this->pending as $pending) {
            if (
                $pending['player'] === $playerName
                && $pending['expiresAt'] >= $now
            ) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Drops expired pending records. Runs once per tick; the list is tiny
     * (bounded by drops inside a 10-tick window) so this is effectively free.
     */
    public function sweepExpired(
        int $now
    ): void {
        foreach ($this->pending as $key => $pending) {
            if ($pending['expiresAt'] < $now) {
                unset($this->pending[$key]);
            }
        }
    }

    /**
     * Attributes a freshly spawned item to the player who dropped it, using the
     * short-lived window recorded when the drop was allowed.
     *
     * Returns '' when the item cannot be attributed to anyone.
     */
    public function resolveOwner(
        ItemEntity $entity,
        int $now
    ): string {
        $position = $entity->getPosition();
        $world = $entity->getWorld();
        $itemName = $entity->getItem()->getName();

        $bestKey = null;
        $bestDistance = 9.0;

        foreach ($this->pending as $key => $pending) {
            if (
                $pending['world'] !== $world
                || $pending['itemName'] !== $itemName
                || $pending['expiresAt'] < $now
            ) {
                continue;
            }

            $dx = $position->x - $pending['x'];
            $dy = $position->y - $pending['y'];
            $dz = $position->z - $pending['z'];

            $distance = $dx * $dx + $dy * $dy + $dz * $dz;

            if ($distance > $bestDistance) {
                continue;
            }

            $bestDistance = $distance;
            $bestKey = $key;
        }

        if ($bestKey === null) {
            return '';
        }

        $owner = $this->pending[$bestKey]['player'];

        unset($this->pending[$bestKey]);

        return $owner;
    }

    /**
     * Records a live item entity and credits its owner.
     *
     * The counter write uses an explicit default instead of `++$map[$key]`
     * because incrementing a missing key emits a warning, and PocketMine
     * escalates warnings to fatal errors. That one missing default crashed
     * the server on the first tracked drop.
     */
    public function noteSpawned(
        ItemEntity $entity,
        string $owner,
        int $now
    ): void {
        if ($owner === '') {
            return;
        }

        if ($entity->isClosed()) {
            return;
        }

        $entityId = $entity->getId();

        if (isset($this->tracked[$entityId])) {
            return;
        }

        $this->tracked[$entityId] = [
            'owner' => $owner,
            'spawnTick' => $now
        ];

        $this->dropCounts[$owner] = ($this->dropCounts[$owner] ?? 0) + 1;
    }

    /**
     * Releases an item entity and debits its owner.
     *
     * Fully idempotent: calling it for an untracked entity, calling it twice,
     * or calling it when the counter is already gone are all safe no-ops. This
     * is what makes racing paths (pickup vs cleanup flag, merge vs despawn,
     * chunk unload vs sweep) unable to corrupt the books.
     */
    public function noteDespawned(
        ItemEntity $entity
    ): void {
        $entityId = $entity->getId();

        $owner = $this->tracked[$entityId]['owner'] ?? null;

        if ($owner === null) {
            return;
        }

        unset($this->tracked[$entityId]);

        if (!isset($this->dropCounts[$owner])) {
            return;
        }

        if (--$this->dropCounts[$owner] <= 0) {
            unset($this->dropCounts[$owner]);
        }
    }

    public function getCount(
        string $playerName
    ): int {
        return $this->dropCounts[strtolower($playerName)] ?? 0;
    }

    /**
     * Whether an item entity has been lying around longer than the TTL.
     *
     * Untracked items count as fresh: an item is only removed once it is
     * provably old.
     */
    public function isExpired(
        ItemEntity $entity,
        int $ttlTicks,
        int $now
    ): bool {
        if ($ttlTicks <= 0) {
            return true;
        }

        if ($entity->isClosed() || $entity->isFlaggedForDespawn()) {
            return false;
        }

        $tracked = $this->tracked[$entity->getId()] ?? null;

        if ($tracked === null) {
            return false;
        }

        return $now - $tracked['spawnTick'] >= $ttlTicks;
    }

    /**
     * Drops pending records that reference an unloaded world. Tracked entities
     * are intentionally left alone: the world close fires despawn events for
     * them, which settle the counters through the normal path.
     */
    public function purgeWorld(
        World $world
    ): void {
        foreach ($this->pending as $key => $pending) {
            if ($pending['world'] === $world) {
                unset($this->pending[$key]);
            }
        }
    }

    public function clear(): void
    {
        $this->dropCounts = [];
        $this->tracked = [];
        $this->pending = [];
    }
}