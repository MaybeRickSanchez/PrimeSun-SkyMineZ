<?php

declare(strict_types=1);

namespace AM\SkyMineZ\miner;

use AM\SkyMineZ\Main;
use pocketmine\utils\Config;

final class MinerManager
{
    /**
     * @var array<string, Miner>
     */
    private array $miners = [];

    private Config $db;

    public function __construct()
    {
        $this->db = Main::getInstance()->getMinerDB();
    }

    public function load(string $playerName): Miner
    {
        $playerName = $this->normalizeName($playerName);

        if (isset($this->miners[$playerName])) {
            return $this->miners[$playerName];
        }

        $data = $this->db->get(
            $playerName,
            null
        );

        if (!is_array($data)) {
            $miner = new Miner(
                $playerName
            );
        } else {
            $miner = new Miner(
                $playerName,
                MinerStates::fromArray($data)
            );
        }

        $this->miners[$playerName] = $miner;

        return $miner;
    }

    public function save(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        if (!isset($this->miners[$playerName])) {
            return;
        }

        $this->db->set(
            $playerName,
            $this->miners[$playerName]->toArray()
        );
    }

    public function unload(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        unset(
            $this->miners[$playerName]
        );
    }

    public function saveAndUnload(string $playerName): void
    {
        $playerName = $this->normalizeName($playerName);

        if (!isset($this->miners[$playerName])) {
            return;
        }

        $this->db->set(
            $playerName,
            $this->miners[$playerName]->toArray()
        );

        $this->db->save();

        unset(
            $this->miners[$playerName]
        );
    }

    public function saveAll(): void
    {
        foreach ($this->miners as $miner) {
            $this->db->set(
                $miner->getPlayerName(),
                $miner->toArray()
            );
        }

        $this->db->save();
    }

    public function get(string $playerName): ?Miner
    {
        $playerName = $this->normalizeName($playerName);

        return $this->miners[$playerName] ?? null;
    }

    public function getOrLoad(string $playerName): Miner
    {
        return $this->get($playerName)
            ?? $this->load($playerName);
    }

    public function has(string $playerName): bool
    {
        return isset(
            $this->miners[
            $this->normalizeName($playerName)
            ]
        );
    }

    public function isLoaded(string $playerName): bool
    {
        return $this->has($playerName);
    }

    public function remove(string $playerName): void
    {
        $this->unload($playerName);
    }

    /**
     * @return array<string, Miner>
     */
    public function getAll(): array
    {
        return $this->miners;
    }

    public function getDatabase(): Config
    {
        return $this->db;
    }

    private function normalizeName(string $playerName): string
    {
        return strtolower($playerName);
    }

    /**
     * @return array<string, array{
     *     mined: int,
     *     deaths: int,
     *     kills: int,
     *     killStreak: int
     * }>
     */
    public function getSnapshot(): array
    {
        $result = [];

        foreach ($this->db->getAll() as $playerName => $data) {
            if (!is_array($data)) {
                continue;
            }

            $playerName = $this->normalizeName(
                (string) $playerName
            );

            $result[$playerName] = [
                'mined' => (int) ($data['mined'] ?? 0),
                'deaths' => (int) ($data['deaths'] ?? 0),
                'kills' => (int) ($data['kills'] ?? 0),
                'killStreak' => (int) ($data['killStreak'] ?? 0)
            ];
        }

        foreach ($this->miners as $miner) {
            $playerName = $miner->getPlayerName();

            $result[$playerName] = [
                'mined' => $miner->getMined(),
                'deaths' => $miner->getDeaths(),
                'kills' => $miner->getKills(),
                'killStreak' => $miner->getKillStreak()
            ];
        }

        return $result;
    }
}