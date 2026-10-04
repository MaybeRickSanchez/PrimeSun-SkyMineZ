<?php

declare(strict_types=1);

namespace AM\SkyMineZ\mine;

use pocketmine\scheduler\Task;
use SplObjectStorage;

class MineManager
{
    /** Default reset interval in seconds */
    private const RESET_INTERVAL = 300;

    /** @var array<string, Mine> */
    private array $mines = [];

    /** @var array<string, MineInfo> */
    private array $mineInfos = [];

    /** @var SplObjectStorage<Task, null> */
    private SplObjectStorage $taskPool;

    public function __construct()
    {
        $this->taskPool = new SplObjectStorage();
    }

    public function register(string $name, Mine $mine, MineInfo $info): void
    {
        $this->mines[$name] = $mine;
        $this->mineInfos[$name] = $info;

        $info->setNextResetAt(time() + self::RESET_INTERVAL);
        $info->spawn();
    }

    public function unregister(string $name): void
    {
        if (isset($this->mineInfos[$name])) {
            $this->mineInfos[$name]->deSpawn();
        }

        unset($this->mines[$name], $this->mineInfos[$name]);
    }

    public function get(string $name): ?Mine
    {
        return $this->mines[$name] ?? null;
    }

    public function getInfo(string $name): ?MineInfo
    {
        return $this->mineInfos[$name] ?? null;
    }

    /** @return array<string, Mine> */
    public function getAll(): array
    {
        return $this->mines;
    }

    public function reset(string $name): void
    {
        $mine = $this->mines[$name] ?? null;
        if ($mine === null) {
            return;
        }

        $mine->reset();

        $info = $this->mineInfos[$name] ?? null;
        if ($info !== null) {
            $info->setNextResetAt(time() + self::RESET_INTERVAL);
            $info->updateTime();
        }
    }

    public function resetAll(): void
    {
        foreach (array_keys($this->mines) as $name) {
            $this->reset($name);
        }
    }

    public function updateAllInfos(): void
    {
        foreach ($this->mineInfos as $info) {
            $info->updateTime();
        }
    }

    public function addTask(Task $task): void
    {
        $this->taskPool->attach($task);
    }

    public function removeTask(Task $task): void
    {
        if ($this->taskPool->contains($task)) {
            $this->taskPool->detach($task);
        }
    }

    public function cancelTask(Task $task): void
    {
        if ($this->taskPool->contains($task)) {
            $task->getHandler()?->cancel();
            $this->taskPool->detach($task);
        }
    }

    public function cancelAllTasks(): void
    {
        foreach ($this->taskPool as $task) {
            $task->getHandler()?->cancel();
        }

        $this->taskPool = new SplObjectStorage();
    }
}