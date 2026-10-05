<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use AM\SkyMineZ\event\MineResetEvent;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\SpreadTask;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use RuntimeException;

/**
 * Owns every mine on the server.
 *
 * Mines live in plugin_data/mines.json and are restored on startup, including a
 * refill so a mine never comes back empty after a restart.
 *
 * {@link MineTask} ticks once per second: it starts whatever refill is due and
 * refreshes the holograms. Refilling itself is spread across many ticks by
 * {@link MineFillTask}, so a 50,000 block mine never blocks the main thread.
 */
final class MineManager
{
    private const FILE_NAME = 'mines.json';

    /** @var array<string, Mine> */
    private array $mines = [];

    private Config $db;

    /** @var TaskHandler<MineTask>|null */
    private ?TaskHandler $task = null;

    public function __construct(
        private Main $main
    ) {
        $this->db = new Config(
            $this->main->getDataFolder() . self::FILE_NAME,
            Config::JSON
        );
    }

    public function load(): void
    {
        $this->despawnAll();

        $this->mines = [];

        $worldManager = $this->main->getServer()
            ->getWorldManager();

        foreach (
            $this->db->getAll() as $name => $data
        ) {
            if (
                !is_string($name)
                || !is_array($data)
                || !self::isStringMap($data)
            ) {
                continue;
            }

            $mine = $this->createFromArray(
                $name,
                $data,
                $worldManager
            );

            if ($mine === null) {
                $this->main->getLogger()->warning(
                    "Skipped malformed mine '{$name}' in " . self::FILE_NAME
                );

                continue;
            }

            $this->mines[$name] = $mine;

            $mine->spawn();
        }

        $this->startTask();
    }

    public function saveAll(): void
    {
        $data = [];

        foreach (
            $this->mines as $name => $mine
        ) {
            $data[$name] = $mine->toArray();
        }

        $this->db->setAll($data);
        $this->db->save();
    }

    public function save(
        string $name
    ): void {
        $mine = $this->mines[$name] ?? null;

        if ($mine === null) {
            return;
        }

        $this->db->set(
            $name,
            $mine->toArray()
        );

        $this->db->save();
    }

    /**
     * @throws RuntimeException when the name is taken or the positions differ
     */
    public function create(
        string $name,
        Position $pos1,
        Position $pos2,
        ?Position $label = null,
        ?int $resetInterval = null
    ): Mine {
        if ($this->has($name)) {
            throw new RuntimeException(
                "Mine '$name' already exists."
            );
        }

        if ($pos1->getWorld() !== $pos2->getWorld()) {
            throw new RuntimeException(
                'Both positions must be in the same world.'
            );
        }

        $interval = $resetInterval ?? $this->getDefaultInterval();

        $mine = new Mine(
            $this->main,
            $name,
            $pos1,
            $pos2,
            $pos1->getWorld(),
            $interval,
            $label
        );

        $this->mines[$name] = $mine;

        $mine->spawn();

        $this->save($name);

        return $mine;
    }

    public function remove(
        string $name
    ): bool {
        $mine = $this->mines[$name] ?? null;

        if ($mine === null) {
            return false;
        }

        $mine->cancelFill();
        $mine->deSpawn();

        unset($this->mines[$name]);

        $this->db->remove($name);
        $this->db->save();

        return true;
    }

    public function has(
        string $name
    ): bool {
        return isset($this->mines[$name]);
    }

    public function get(
        string $name
    ): ?Mine {
        return $this->mines[$name] ?? null;
    }

    /**
     * @return array<string, Mine>
     */
    public function getAll(): array
    {
        return $this->mines;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->mines);
    }

    public function count(): int
    {
        return count($this->mines);
    }

    /**
     * Starts a refill. The blocks are written over several ticks.
     *
     * Raises {@link MineResetEvent} first, so listeners can veto the refill (for
     * example during a maintenance window) before any block is written.
     */
    public function reset(
        string $name,
        string $reason = 'manual'
    ): bool {
        $mine = $this->mines[$name] ?? null;

        if ($mine === null) {
            return false;
        }

        if (!MineResetEvent::hasHandlers()) {
            return $mine->reset($reason);
        }

        $event = new MineResetEvent($mine, $reason);

        $event->call();

        if ($event->isCancelled()) {
            return false;
        }

        return $mine->reset($reason);
    }

    /**
     * Starts a refill on every mine that is not already being refilled.
     *
     * @return list<string> names of the mines that were started
     */
    public function resetAll(
        string $reason = 'manual'
    ): array {
        $started = [];

        foreach (
            $this->mines as $name => $mine
        ) {
            if ($mine->reset($reason)) {
                $started[] = (string) $name;
            }
        }

        return $started;
    }

    /**
     * Fills every restored mine right away, so a restart does not leave the
     * mine empty until the first timer tick.
     */
    public function fillRestored(): void
    {
        foreach (
            $this->mines as $mine
        ) {
            $mine->reset('startup');
        }
    }

    public function getMineAt(
        World $world,
        Vector3 $position
    ): ?Mine {
        foreach (
            $this->mines as $mine
        ) {
            if (
                $mine->getWorld() !== $world
            ) {
                continue;
            }

            if ($mine->isIn($position)) {
                return $mine;
            }
        }

        return null;
    }

    /**
     * Refreshes every mine's countdown line.
     */
    public function tickHolograms(): void
    {
        foreach (
            $this->mines as $mine
        ) {
            $mine->updateHologram();
        }
    }

    public function despawnAll(): void
    {
        foreach (
            $this->mines as $mine
        ) {
            $mine->cancelFill();
            $mine->deSpawn();
        }
    }

    public function getDatabase(): Config
    {
        return $this->db;
    }

    /**
     * One second tick: refill whatever is due and respawn holograms for players
     * who just walked into range.
     */
    public function onTick(): void
    {
        $now = time();

        foreach (
            $this->mines as $mine
        ) {
            if (
                $mine->getResetInterval() <= 0
                || $mine->isFilling()
            ) {
                continue;
            }

            $dueAt = $mine->getInfo()->getNextResetAt();

            if ($dueAt <= 0) {
                $mine->getInfo()->setNextResetAt(
                    $mine->nextResetTimestamp()
                );

                continue;
            }

            if ($dueAt > $now) {
                continue;
            }

            $mine->reset('scheduled');
        }
    }

    /**
     * Shows the mine hologram to a player who just spawned in.
     */
    public function spawnTo(
        Player $player
    ): void {
        SpreadTask::spread(
            $this->main,
            $this->mines,
            2,
            function(
                mixed $mine
            ) use ($player): void {
                if (
                    !$mine instanceof Mine
                    || !$mine->getInfo()->isSpawned()
                ) {
                    return;
                }

                if (
                    $mine->getWorld() !== $player->getWorld()
                ) {
                    return;
                }

                $info = $mine->getInfo();
                $position = $info->getPosition();

                if ($position === null) {
                    return;
                }

                $particle = $info->getParticle();

                $particle?->spawn($player);
            }
        );
    }

    private function startTask(): void
    {
        $this->task?->cancel();

        $this->task = $this->main->getScheduler()->scheduleRepeatingTask(
            new MineTask($this),
            MineTask::TICK_INTERVAL
        );
    }

    private function getDefaultInterval(): int
    {
        return max(
            0,
            $this->main
                ->getConfigManager()
                ->getInt('mines.reset-interval', 300)
        );
    }

    /**
     * JSON objects decode to string-keyed arrays, but a hand-edited file can
     * contain anything. This keeps the rest of the loader free of cast checks.
     *
     * @param array<mixed> $array
     *
     * @phpstan-assert-if-true array<string, mixed> $array
     */
    private static function isStringMap(
        array $array
    ): bool {
        foreach (
            array_keys($array) as $key
        ) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createFromArray(
        string $name,
        array $data,
        WorldManager $worldManager
    ): ?Mine {
        if (
            !isset(
                $data['world'],
                $data['pos1'],
                $data['pos2']
            )
            || !is_string($data['world'])
        ) {
            return null;
        }

        $pos1 = $data['pos1'];
        $pos2 = $data['pos2'];

        if (
            !is_array($pos1)
            || !is_array($pos2)
            || !isset($pos1[0], $pos1[1], $pos1[2])
            || !isset($pos2[0], $pos2[1], $pos2[2])
            || !is_numeric($pos1[0])
            || !is_numeric($pos1[1])
            || !is_numeric($pos1[2])
            || !is_numeric($pos2[0])
            || !is_numeric($pos2[1])
            || !is_numeric($pos2[2])
        ) {
            return null;
        }

        $world = $this->resolveWorld(
            (string) $data['world'],
            $worldManager
        );

        if ($world === null) {
            return null;
        }

        $label = $this->readLabel(
            $data['label'] ?? null,
            $worldManager
        );

        $interval = isset($data['resetInterval'])
            && is_numeric($data['resetInterval'])
            ? (int) $data['resetInterval']
            : $this->getDefaultInterval();

        $mine = new Mine(
            $this->main,
            $name,
            new Vector3(
                (float) $pos1[0],
                (float) $pos1[1],
                (float) $pos1[2]
            ),
            new Vector3(
                (float) $pos2[0],
                (float) $pos2[1],
                (float) $pos2[2]
            ),
            $world,
            $interval,
            $label
        );

        foreach (
            (array) ($data['blocks'] ?? []) as $blockData
        ) {
            if (
                !is_array($blockData)
                || !self::isStringMap($blockData)
            ) {
                continue;
            }

            $block = MineBlock::fromArray($blockData);

            if ($block !== null) {
                $mine->addBlock($block);
            }
        }

        /*
         * Spread the first refill over the first half of the interval so a
         * restart does not refill every mine on the same second.
         */
        $mine->getInfo()->setNextResetAt(
            $interval > 0
                ? time() + mt_rand(
                    1,
                    max(2, (int) floor($interval / 2))
                )
                : 0
        );

        return $mine;
    }

    /**
     * @param mixed $data
     */
    private function readLabel(
        mixed $data,
        WorldManager $worldManager
    ): ?Position {
        if (
            !is_array($data)
            || !isset(
                $data[0],
                $data[1],
                $data[2],
                $data[3]
            )
            || !is_string($data[0])
            || !is_numeric($data[1])
            || !is_numeric($data[2])
            || !is_numeric($data[3])
        ) {
            return null;
        }

        $world = $this->resolveWorld(
            (string) $data[0],
            $worldManager
        );

        if ($world === null) {
            return null;
        }

        return new Position(
            (float) $data[1],
            (float) $data[2],
            (float) $data[3],
            $world
        );
    }

    private function resolveWorld(
        string $name,
        WorldManager $worldManager
    ): ?World {
        $world = $worldManager->getWorldByName($name);

        if ($world !== null) {
            return $world;
        }

        if ($worldManager->isWorldGenerated($name)) {
            $worldManager->loadWorld($name);

            return $worldManager->getWorldByName($name);
        }

        return null;
    }
}