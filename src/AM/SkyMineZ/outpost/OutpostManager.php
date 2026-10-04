<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\economy\GoldEconomy;
use AM\SkyMineZ\Main;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SplObjectStorage;

class OutpostManager
{
    private const TICK_INTERVAL = 20;

    /** @var array<string, Outpost> */
    private array $outposts = [];

    /** @var SplObjectStorage<Task, null> */
    private SplObjectStorage $taskPool;

    public function __construct(private GoldEconomy $goldEconomy)
    {
        $this->taskPool = new SplObjectStorage();
        $this->startTickLoop();
    }

    public function register(string $name, Outpost $outpost): void
    {
        $this->outposts[$name] = $outpost;
        $outpost->spawn();
    }

    public function unregister(string $name): void
    {
        if (isset($this->outposts[$name])) {
            $this->outposts[$name]->deSpawn();
        }

        unset($this->outposts[$name]);
    }

    public function get(string $name): ?Outpost
    {
        return $this->outposts[$name] ?? null;
    }

    /** @return array<string, Outpost> */
    public function getAll(): array
    {
        return $this->outposts;
    }

    public function handleJump(Player $player): void
    {
        $pos = $player->getPosition();
        $name = $player->getName();

        foreach ($this->outposts as $outpost) {
            if ($outpost->getBox()->isIn($pos)) {
                $outpost->addCandidate($name);
            }
        }
    }

    public function handleQuit(Player $player): void
    {
        $name = $player->getName();

        foreach ($this->outposts as $outpost) {
            $outpost->removeCandidate($name);
        }
    }

    public function addTask(Task $task): void
    {
        $this->taskPool->attach($task);
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

    private function startTickLoop(): void
    {
        $task = new class($this) extends Task {
            public function __construct(private OutpostManager $manager)
            {
            }

            public function onRun(): void
            {
                $this->manager->tickAll();
            }
        };

        Main::getInstance()
            ->getScheduler()
            ->scheduleRepeatingTask($task, self::TICK_INTERVAL);

        $this->taskPool->attach($task);
    }

    public function tickAll(): void
    {
        $now = time();

        foreach ($this->outposts as $outpost) {
            $previousOwner = $outpost->getOwner();
            $previousState = $outpost->getState();

            $outpost->tick($now);

            $this->handleStateChange($outpost, $previousState);
            $this->handleOwnerChange($outpost, $previousOwner);
            $this->handleGold($outpost, $now);
        }
    }

    private function handleStateChange(Outpost $outpost, string $previousState): void
    {
        if (
            $previousState === Outpost::STATE_COOLDOWN
            && $outpost->getState() === Outpost::STATE_CAPTABLE
        ) {
            $this->broadcast(
                TextFormat::GREEN . "+ The outpost "
                . TextFormat::YELLOW . $outpost->getName()
                . TextFormat::GREEN . " is capturable again!"
            );
        }
    }

    private function handleOwnerChange(Outpost $outpost, ?string $previousOwner): void
    {
        $newOwner = $outpost->getOwner();

        if ($previousOwner === $newOwner || $newOwner === null) {
            return;
        }

        if ($previousOwner === null) {
            $this->broadcast(
                TextFormat::GOLD . "+ "
                . TextFormat::YELLOW . $newOwner
                . TextFormat::GOLD . " has captured the outpost "
                . TextFormat::YELLOW . $outpost->getName()
                . TextFormat::GOLD . "!"
            );

            return;
        }

        $this->broadcast(
            TextFormat::GOLD . "+ "
            . TextFormat::YELLOW . $newOwner
            . TextFormat::GOLD . " has taken the outpost "
            . TextFormat::YELLOW . $outpost->getName()
            . TextFormat::GOLD . " from "
            . TextFormat::YELLOW . $previousOwner
            . TextFormat::GOLD . "!"
        );
    }

    private function handleGold(Outpost $outpost, int $now): void
    {
        if (!$outpost->isGoldDue($now)) {
            return;
        }

        $owner = $outpost->getOwner();
        if ($owner === null) {
            return;
        }

        $this->goldEconomy->add($owner, Outpost::GOLD_REWARD);

        $player = Server::getInstance()->getPlayerExact($owner);

        // AI is dump BTW
        $player?->sendMessage(
            TextFormat::GOLD . "+ You received "
            . TextFormat::YELLOW . Outpost::GOLD_REWARD
            . TextFormat::GOLD . " gold from "
            . TextFormat::YELLOW . $outpost->getName()
            . TextFormat::GOLD . "!"
        );
    }

    private function broadcast(string $message): void
    {
        Server::getInstance()->broadcastMessage($message);
    }
}