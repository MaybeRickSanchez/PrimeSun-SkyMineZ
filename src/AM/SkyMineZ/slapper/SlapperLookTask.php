<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;

/**
 * Makes slapper NPCs face whoever is closest to them.
 *
 * Runs every few seconds, not every tick: a head turn nobody is watching
 * closely does not need tick precision. Work per run is bounded — one
 * world-player scan per slapper, with an early exit for empty worlds — and
 * rotation packets only go out when the target actually changes, so an idle
 * slapper is silent.
 */
final class SlapperLookTask extends Task
{
    public const INTERVAL = 100;

    private const LOOK_RANGE = 10.0;

    /** @var array<string, string> slapper name => last target player name */
    private array $lastTargets = [];

    /**
     * Drops the cached target, e.g. when the slapper is deleted. Otherwise
     * the entry lingers until the next run notices the missing entity.
     */
    public function forget(
        string $name
    ): void {
        unset($this->lastTargets[$name]);
    }

    public function __construct(
        private SlapperManager $manager
    ) {
    }

    public function onRun(): void
    {
        foreach ($this->manager->getSlappers() as $name => $slapper) {
            $entity = $slapper->getEntity();

            if ($entity === null || $entity->isClosed()) {
                unset($this->lastTargets[$name]);

                continue;
            }

            $target = $this->nearestPlayer($slapper);

            if ($target === null) {
                unset($this->lastTargets[$name]);

                continue;
            }

            if (($this->lastTargets[$name] ?? null) === $target->getName()) {
                continue;
            }

            $this->lastTargets[$name] = $target->getName();

            $entity->lookAt(
                $target->getPosition()->add(0, 1.5, 0)
            );
        }
    }

    private function nearestPlayer(
        Slapper $slapper
    ): ?Player {
        $location = $slapper->getLocation();
        $world = $location->getWorld();

        $players = $world->getPlayers();

        if ($players === []) {
            return null;
        }

        $best = null;
        $bestDistance = self::LOOK_RANGE * self::LOOK_RANGE;

        foreach ($players as $player) {
            if (!$player->isConnected() || $player->isClosed()) {
                continue;
            }

            $distance = $player->getPosition()->distanceSquared($location);

            if ($distance >= $bestDistance) {
                continue;
            }

            $bestDistance = $distance;
            $best = $player;
        }

        return $best;
    }
}