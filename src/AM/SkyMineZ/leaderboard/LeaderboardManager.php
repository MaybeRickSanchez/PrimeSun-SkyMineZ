<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\scorehud\ServerAddress;
use AM\SkyMineZ\useless\Arrays;
use AM\SkyMineZ\useless\SpreadTask;
use AM\SkyMineZ\useless\Worlds;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\WorldManager;
use RuntimeException;

/**
 * Owns every leaderboard and refreshes them from the economy and miner data.
 *
 * Boards live in plugin_data/leaderboards.json. The top ten of each requested type
 * is computed with a bounded priority queue, so refreshing costs O(n log 10)
 * rather than sorting every player's balance.
 *
 * {@link LeaderboardTask} calls {@link refreshAll()} on an interval; a manual
 * /lb refresh does the same.
 */
final class LeaderboardManager
{
    private const FILE_NAME = 'leaderboards.json';
    private const TOP_LIMIT = 10;

    /** @var array<string, Leaderboard> */
    private array $leaderboards = [];

    private Config $db;

    public function __construct(
        private Main $main
    ) {
        $this->db = new Config(
            $this->main->getDataFolder() . self::FILE_NAME,
            Config::JSON
        );

        $this->main->getScheduler()->scheduleRepeatingTask(
            new LeaderboardTask($this),
            LeaderboardTask::INTERVAL
        );
    }

    public function load(): void
    {
        $this->despawnAll();

        $this->leaderboards = [];

        $worldManager = $this->main->getServer()
            ->getWorldManager();

        foreach (
            $this->db->getAll() as $name => $data
        ) {
            if (
                !is_string($name)
                || !is_array($data)
                || !Arrays::isStringMap($data)
            ) {
                continue;
            }

            $leaderboard = $this->createFromArray(
                $name,
                $data,
                $worldManager
            );

            if ($leaderboard === null) {
                $this->main->getLogger()->warning(
                    "Skipped malformed leaderboard '{$name}' in " . self::FILE_NAME
                );

                continue;
            }

            $this->leaderboards[$name] = $leaderboard;
        }

        $this->refreshAll();
        $this->spawnAll();
    }

    public function saveAll(): void
    {
        $data = [];

        foreach (
            $this->leaderboards as $name => $leaderboard
        ) {
            $data[$name] = $leaderboard->toArray();
        }

        $this->db->setAll($data);
        $this->db->save();
    }

    public function save(
        string $name
    ): void {
        $leaderboard = $this->leaderboards[$name] ?? null;

        if ($leaderboard === null) {
            return;
        }

        $this->db->set(
            $name,
            $leaderboard->toArray()
        );

        $this->db->save();
    }

    /**
     * @throws RuntimeException when the name is taken
     * @throws \InvalidArgumentException when the type is unknown
     */
    public function add(
        string $name,
        string $type,
        Position $position,
        ?string $title = null
    ): Leaderboard {
        if (isset($this->leaderboards[$name])) {
            throw new RuntimeException(
                "Leaderboard '$name' already exists."
            );
        }

        if (!Leaderboard::isValidType($type)) {
            throw new \InvalidArgumentException(
                "Unknown leaderboard type: $type"
            );
        }

        $leaderboard = new Leaderboard(
            $name,
            $type,
            $position,
            $title
        );

        $this->leaderboards[$name] = $leaderboard;

        $this->refreshAll();

        $leaderboard->spawn();

        $this->save($name);

        return $leaderboard;
    }

    public function remove(
        string $name
    ): bool {
        $leaderboard = $this->leaderboards[$name] ?? null;

        if ($leaderboard === null) {
            return false;
        }

        $leaderboard->despawn();

        unset($this->leaderboards[$name]);

        $this->db->remove($name);
        $this->db->save();

        return true;
    }

    public function get(
        string $name
    ): ?Leaderboard {
        return $this->leaderboards[$name] ?? null;
    }

    public function has(
        string $name
    ): bool {
        return isset($this->leaderboards[$name]);
    }

    /**
     * @return array<string, Leaderboard>
     */
    public function getAll(): array
    {
        return $this->leaderboards;
    }

    public function count(): int
    {
        return count($this->leaderboards);
    }

    public function spawnAll(): void
    {
        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->spawn();
        }
    }

    public function despawnAll(): void
    {
        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->despawn();
        }
    }

    /**
     * Pushes every board to a player who just spawned in. Without this a player
     * who joined after startup would never see a board until the next refresh.
     */
    public function spawnTo(
        Player $player
    ): void {
        SpreadTask::spread(
            $this->main,
            $this->leaderboards,
            2,
            function(
                mixed $leaderboard
            ) use ($player): void {
                if ($leaderboard instanceof Leaderboard) {
                    $leaderboard->spawnTo($player);
                }
            }
        );
    }

    /**
     * Recomputes and re-renders every board.
     */
    public function refreshAll(): void
    {
        if ($this->leaderboards === []) {
            return;
        }

        $needed = [];

        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $needed[$leaderboard->getType()] = true;
        }

        $serverAddress = ServerAddress::of($this->main);
        $top = [];

        foreach (
            array_keys($needed) as $type
        ) {
            $top[$type] = match ($type) {
                Leaderboard::TYPE_MONEY => $this->getTopNumeric(
                    $this->main->getMoneyEconomy()->getSnapshot()
                ),
                Leaderboard::TYPE_GOLD => $this->getTopNumeric(
                    $this->main->getGoldEconomy()->getSnapshot()
                ),
                default => $this->getTopField(
                    $this->main->getMinerManager()->getSnapshot(),
                    $type
                )
            };
        }

        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->update(
                $top[$leaderboard->getType()] ?? [],
                $serverAddress
            );
        }
    }

    /**
     * @param array<string, int> $data
     *
     * @return list<array{name: string, value: int|float}>
     */
    private function getTopNumeric(
        array $data
    ): array {
        $candidates = [];

        foreach (
            $data as $name => $amount
        ) {
            if ($amount <= 0) {
                continue;
            }

            $candidates[] = [
                'name' => (string) $name,
                'value' => $amount
            ];
        }

        return $this->takeTop($candidates);
    }

    /**
     * @param array<string, array<string, int>> $data
     *
     * @return list<array{name: string, value: int|float}>
     */
    private function getTopField(
        array $data,
        string $field
    ): array {
        $candidates = [];

        foreach (
            $data as $name => $stats
        ) {
            $value = $stats[$field] ?? 0;

            if ($value <= 0) {
                continue;
            }

            $candidates[] = [
                'name' => (string) $name,
                'value' => $value
            ];
        }

        return $this->takeTop($candidates);
    }

    /**
     * Sorts descending and keeps the first ten.
     *
     * A plain usort beats a bounded priority queue here: the board is rebuilt at
     * most once every ten minutes, PHP's sort is a tight C loop, and the code
     * stays obvious. Skipping the players with a zero value keeps the sort input
     * small on a fresh server.
     *
     * @param list<array{name: string, value: int|float}> $candidates
     *
     * @return list<array{name: string, value: int|float}>
     */
    private function takeTop(
        array $candidates
    ): array {
        usort(
            $candidates,
            static function(
                array $a,
                array $b
            ): int {
                return $b['value'] <=> $a['value'];
            }
        );

        return array_slice(
            $candidates,
            0,
            self::TOP_LIMIT
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createFromArray(
        string $name,
        array $data,
        WorldManager $worldManager
    ): ?Leaderboard {
        if (
            !isset(
                $data['type'],
                $data['world'],
                $data['x'],
                $data['y'],
                $data['z']
            )
            || !is_string($data['type'])
            || !is_string($data['world'])
            || !is_numeric($data['x'])
            || !is_numeric($data['y'])
            || !is_numeric($data['z'])
        ) {
            return null;
        }

        if (!Leaderboard::isValidType($data['type'])) {
            return null;
        }

        $world = Worlds::resolve(
            $worldManager,
            $data['world']
            );

        if ($world === null) {
            return null;
        }

        try {
            return new Leaderboard(
                $name,
                $data['type'],
                new Position(
                    (float) $data['x'],
                    (float) $data['y'],
                    (float) $data['z'],
                    $world
                ),
                isset($data['title']) && is_string($data['title'])
                    ? $data['title']
                    : null
            );
        } catch (\Throwable) {
            return null;
        }
    }

}