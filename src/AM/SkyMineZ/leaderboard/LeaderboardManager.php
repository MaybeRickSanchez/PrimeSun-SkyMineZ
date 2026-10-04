<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\Main;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use SplPriorityQueue;

final class LeaderboardManager
{
    private const TOP_LIMIT = 10;

    /**
     * @var array<string, Leaderboard>
     */
    private array $leaderboards = [];

    private Config $db;

    private LeaderboardTask $task;

    public function __construct(
        private Main $main
    ) {
        $this->db = new Config(
            $this->main->getDataFolder() .
            'leaderboards.json',
            Config::JSON
        );

        $this->task = new LeaderboardTask(
            $this
        );

        $this->main
            ->getScheduler()
            ->scheduleRepeatingTask(
                $this->task,
                20 * 60 * 10
            );
    }

    public function load(): void
    {
        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->despawn();
        }

        $this->leaderboards = [];

        foreach (
            $this->db->getAll()
            as $name => $data
        ) {
            if (
                !is_string($name) ||
                !is_array($data)
            ) {
                continue;
            }

            $leaderboard =
                $this->createFromArray(
                    $name,
                    $data
                );

            if ($leaderboard === null) {
                continue;
            }

            $this->leaderboards[$name] =
                $leaderboard;
        }

        $this->refreshAll();
        $this->spawnAll();
    }

    public function addLeaderboard(
        string $name,
        string $type,
        Position $position,
        ?string $title = null
    ): Leaderboard {
        if (
            isset(
                $this->leaderboards[$name]
            )
        ) {
            throw new RuntimeException(
                "Leaderboard '{$name}' already exists."
            );
        }

        $leaderboard = new Leaderboard(
            $name,
            $type,
            $position,
            $title
        );

        $this->leaderboards[$name] =
            $leaderboard;

        $this->refreshAll();
        $leaderboard->spawn();

        return $leaderboard;
    }

    public function removeLeaderboard(
        string $name
    ): bool {
        $leaderboard =
            $this->getLeaderboard($name);

        if ($leaderboard === null) {
            return false;
        }

        $leaderboard->despawn();

        unset(
            $this->leaderboards[$name]
        );

        $this->db->remove(
            $name
        );

        $this->db->save();

        return true;
    }

    public function getLeaderboard(
        string $name
    ): ?Leaderboard {
        return $this->leaderboards[$name] ?? null;
    }

    /**
     * @return array<string, Leaderboard>
     */
    public function getLeaderboards(): array
    {
        return $this->leaderboards;
    }

    public function spawnAll(): void
    {
        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->spawn();
        }
    }

    public function spawnToPlayer(
        \pocketmine\player\Player $player
    ): void {
        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->spawnTo(
                $player
            );
        }
    }

    public function refreshAll(): void
    {
        if ($this->leaderboards === []) {
            return;
        }

        $types = [];

        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $types[
            $leaderboard->getType()
            ] = true;
        }

        $top = [];

        $minerSnapshot = null;

        foreach (
            array_keys($types) as $type
        ) {
            switch ($type) {
                case Leaderboard::TYPE_MONEY:
                    $top[$type] =
                        $this->getTopNumeric(
                            $this->main
                                ->getMoneyEconomy()
                                ->getSnapshot()
                        );
                    break;

                case Leaderboard::TYPE_GOLD:
                    $top[$type] =
                        $this->getTopNumeric(
                            $this->main
                                ->getGoldEconomy()
                                ->getSnapshot()
                        );
                    break;

                case Leaderboard::TYPE_MINED:
                case Leaderboard::TYPE_DEATHS:
                case Leaderboard::TYPE_KILLS:
                    if ($minerSnapshot === null) {
                        $minerSnapshot =
                            $this->main
                                ->getMinerManager()
                                ->getSnapshot();
                    }

                    $top[$type] =
                        $this->getTopField(
                            $minerSnapshot,
                            $type
                        );
                    break;
            }
        }

        $serverAddress =
            $this->getServerAddress();

        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $leaderboard->update(
                $top[
                $leaderboard->getType()
                ] ?? [],
                $serverAddress
            );
        }
    }

    public function save(
        string $name
    ): void {
        $leaderboard =
            $this->getLeaderboard($name);

        if ($leaderboard === null) {
            return;
        }

        $this->db->set(
            $name,
            $leaderboard->toArray()
        );

        $this->db->save();
    }

    public function saveAll(): void
    {
        $existing =
            $this->db->getAll();

        foreach (
            array_keys($existing) as $name
        ) {
            if (
                !isset(
                    $this->leaderboards[$name]
                )
            ) {
                $this->db->remove($name);
            }
        }

        foreach (
            $this->leaderboards as $leaderboard
        ) {
            $this->db->set(
                $leaderboard->getName(),
                $leaderboard->toArray()
            );
        }

        $this->db->save();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{name: string, value: int|float}>
     */
    private function getTopNumeric(
        array $data
    ): array {
        $heap = new SplPriorityQueue();

        $heap->setExtractFlags(
            SplPriorityQueue::EXTR_BOTH
        );

        foreach (
            $data as $name => $value
        ) {
            if (!is_numeric($value)) {
                continue;
            }

            $value = (float) $value;

            if ($value <= 0) {
                continue;
            }

            $heap->insert(
                [
                    'name' => (string) $name,
                    'value' => $value
                ],
                -$value
            );

            if (
                $heap->count() >
                self::TOP_LIMIT
            ) {
                $heap->extract();
            }
        }

        return $this->extractHeap(
            $heap
        );
    }

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return list<array{name: string, value: int|float}>
     */
    private function getTopField(
        array $data,
        string $field
    ): array {
        $heap = new SplPriorityQueue();

        $heap->setExtractFlags(
            SplPriorityQueue::EXTR_BOTH
        );

        foreach (
            $data as $name => $stats
        ) {
            if (!is_array($stats)) {
                continue;
            }

            $value =
                $stats[$field] ?? 0;

            if (!is_numeric($value)) {
                continue;
            }

            $value = (float) $value;

            if ($value <= 0) {
                continue;
            }

            $heap->insert(
                [
                    'name' => (string) $name,
                    'value' => $value
                ],
                -$value
            );

            if (
                $heap->count() >
                self::TOP_LIMIT
            ) {
                $heap->extract();
            }
        }

        return $this->extractHeap(
            $heap
        );
    }

    /**
     * @return list<array{name: string, value: int|float}>
     */
    private function extractHeap(
        SplPriorityQueue $heap
    ): array {
        $result = [];

        while (!$heap->isEmpty()) {
            $result[] =
                $heap->extract()['data'];
        }

        usort(
            $result,
            static function (
                array $a,
                array $b
            ): int {
                return
                    $b['value'] <=>
                    $a['value'];
            }
        );

        return $result;
    }

    private function createFromArray(
        string $name,
        array $data
    ): ?Leaderboard {
        if (
            !isset(
                $data['type'],
                $data['world'],
                $data['x'],
                $data['y'],
                $data['z']
            )
        ) {
            return null;
        }

        $world =
            $this->resolveWorld(
                (string) $data['world']
            );

        if ($world === null) {
            return null;
        }

        try {
            return new Leaderboard(
                $name,
                (string) $data['type'],
                new Position(
                    (float) $data['x'],
                    (float) $data['y'],
                    (float) $data['z'],
                    $world
                ),
                isset($data['title'])
                    ? (string) $data['title']
                    : null
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveWorld(
        string $name
    ): ?World {
        $worldManager =
            $this->main
                ->getServer()
                ->getWorldManager();

        $world =
            $worldManager
                ->getWorldByName($name);

        if ($world !== null) {
            return $world;
        }

        if (
            $worldManager->isWorldGenerated(
                $name
            )
        ) {
            $worldManager->loadWorld(
                $name
            );

            return $worldManager
                ->getWorldByName($name);
        }

        return null;
    }

    private function getServerAddress(): string
    {
        $server =
            $this->main->getServer();

        $ip = $server->getIp();
        $port = $server->getPort();

        if (
            $port > 0 &&
            $port !== 19132
        ) {
            return $ip . ':' . $port;
        }

        return $ip;
    }

    public function getMain(): Main
    {
        return $this->main;
    }
}