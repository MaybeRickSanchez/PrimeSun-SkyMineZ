<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\miner\Miner;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

class ScoreHud implements Listener
{
    private const OBJECTIVE = 'skymine';
    private const UPDATE_TICKS = 20;

    private const MODE_MIDDLE = 'middle';
    private const MODE_LOBBY = 'lobby';

    private const SPAWN_WELCOME_SECONDS = 60;
    private const SPAWN_LEAVE_SECONDS = 5;
    private const SPAWN_RADIUS = 3.0;

    private Main $main;

    private MiddleLobbyScoreHud $middleHud;
    private LobbyScoreHud $lobbyHud;

    private ScoreHudTask $task;

    /**
     * @var array<string, string>
     */
    private array $modes = [];

    /**
     * @var array<string, int>
     */
    private array $spawnEnteredAt = [];

    /**
     * @var array<string, int|null>
     */
    private array $leftSpawnAt = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $lines = [];

    public function __construct(Main $main)
    {
        $this->main = $main;

        $this->middleHud = new MiddleLobbyScoreHud(
            $this->main
        );

        $this->lobbyHud = new LobbyScoreHud(
            $this->main
        );

        $this->task = new ScoreHudTask($this);

        $this->main->getScheduler()->scheduleRepeatingTask(
            $this->task,
            self::UPDATE_TICKS
        );
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $this->initializePlayer($player);
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        $this->remove($player);
    }

    public function tick(): void
    {
        foreach ($this->main->getServer()->getOnlinePlayers() as $player) {
            $this->updatePlayer($player);
        }
    }

    public function initializePlayer(Player $player): void
    {
        $name = $this->key($player);

        unset(
            $this->spawnEnteredAt[$name],
            $this->leftSpawnAt[$name],
            $this->lines[$name]
        );

        if ($this->isAtServerSpawn($player)) {
            $this->modes[$name] = self::MODE_MIDDLE;
            $this->spawnEnteredAt[$name] = time();
            $this->leftSpawnAt[$name] = null;

            $this->showMiddle($player);
        } else {
            $this->modes[$name] = self::MODE_LOBBY;
            $this->leftSpawnAt[$name] = null;

            $this->showLobby($player);
        }
    }

    public function remove(Player $player): void
    {
        $name = $this->key($player);

        $this->removeScoreboard($player);

        unset(
            $this->modes[$name],
            $this->spawnEnteredAt[$name],
            $this->leftSpawnAt[$name],
            $this->lines[$name]
        );
    }

    public function updatePlayer(Player $player): void
    {
        if (!$player->isConnected()) {
            return;
        }

        $name = $this->key($player);
        $now = time();
        $atSpawn = $this->isAtServerSpawn($player);

        if (!isset($this->modes[$name])) {
            $this->initializePlayer($player);
            return;
        }

        if ($atSpawn) {
            $this->leftSpawnAt[$name] = null;

            if ($this->modes[$name] !== self::MODE_MIDDLE) {
                $this->modes[$name] = self::MODE_MIDDLE;
                $this->spawnEnteredAt[$name] = $now;

                $this->showMiddle($player);

                return;
            }

            $enteredAt = $this->spawnEnteredAt[$name] ?? $now;

            if (
                ($now - $enteredAt) >=
                self::SPAWN_WELCOME_SECONDS
            ) {
                if ($this->modes[$name] !== self::MODE_LOBBY) {
                    $this->modes[$name] = self::MODE_LOBBY;

                    $this->showLobby($player);

                    return;
                }

                $this->showLobby($player);
                return;
            }

            $this->showMiddle($player);

            return;
        }

        if (
            $this->modes[$name] === self::MODE_MIDDLE
        ) {
            if ($this->leftSpawnAt[$name] === null) {
                $this->leftSpawnAt[$name] = $now;
            }

            if (
                ($now - $this->leftSpawnAt[$name]) >=
                self::SPAWN_LEAVE_SECONDS
            ) {
                $this->modes[$name] = self::MODE_LOBBY;
                $this->spawnEnteredAt[$name] = 0;

                $this->showLobby($player);

                return;
            }

            $this->showMiddle($player);

            return;
        }

        $this->showLobby($player);
    }

    private function showMiddle(Player $player): void
    {
        $lines = $this->middleHud->getLines();

        $this->updateScoreboard(
            $player,
            $this->middleHud->getTitle(),
            $lines
        );
    }

    private function showLobby(Player $player): void
    {
        $lines = $this->lobbyHud->getLines(
            $player
        );

        $this->updateScoreboard(
            $player,
            $this->lobbyHud->getTitle(),
            $lines
        );
    }

    /**
     * @param list<string> $lines
     */
    private function updateScoreboard(
        Player $player,
        string $title,
        array $lines
    ): void {
        $name = $this->key($player);

        if (!isset($this->lines[$name])) {
            $this->createScoreboard(
                $player,
                $title,
                $lines
            );

            return;
        }

        if (
            $this->modes[$name] === self::MODE_MIDDLE &&
            $title !== $this->middleHud->getTitle()
        ) {
            $this->createScoreboard(
                $player,
                $title,
                $lines
            );

            return;
        }

        $oldLines = $this->lines[$name];

        $this->sendChangedLines(
            $player,
            $oldLines,
            $lines
        );

        $this->lines[$name] = $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function createScoreboard(
        Player $player,
        string $title,
        array $lines
    ): void {
        $this->removeScoreboard($player);

        $packet = SetDisplayObjectivePacket::create(
            SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR,
            self::OBJECTIVE,
            $title,
            'dummy',
            SetDisplayObjectivePacket::SORT_ORDER_DESCENDING
        );

        $player
            ->getNetworkSession()
            ->sendDataPacket($packet);

        $this->sendChangedLines(
            $player,
            [],
            $lines
        );

        $this->lines[
        $this->key($player)
        ] = $lines;
    }

    /**
     * @param list<string> $oldLines
     * @param list<string> $newLines
     */
    private function sendChangedLines(
        Player $player,
        array $oldLines,
        array $newLines
    ): void {
        $entries = [];

        $max = max(
            count($oldLines),
            count($newLines)
        );

        for ($index = 0; $index < $max; ++$index) {
            $old = $oldLines[$index] ?? null;
            $new = $newLines[$index] ?? null;

            if ($old === $new) {
                continue;
            }

            if ($new !== null) {
                $entry = new ScorePacketEntry();

                $entry->objectiveName =
                    self::OBJECTIVE;

                $entry->type =
                    ScorePacketEntry::TYPE_FAKE_PLAYER;

                $entry->customName =
                    $this->makeUniqueLine(
                        $new,
                        $index
                    );

                $entry->score =
                    count($newLines) - $index;

                $entry->scoreboardId =
                    $index + 1;

                $entries[] = $entry;

                continue;
            }

            if ($old !== null) {
                $entry = new ScorePacketEntry();

                $entry->objectiveName =
                    self::OBJECTIVE;

                $entry->type =
                    ScorePacketEntry::TYPE_FAKE_PLAYER;

                $entry->customName =
                    $this->makeUniqueLine(
                        $old,
                        $index
                    );

                $entry->score =
                    count($oldLines) - $index;

                $entry->scoreboardId =
                    $index + 1;

                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return;
        }

        $packet = new SetScorePacket();

        $packet->type =
            SetScorePacket::TYPE_CHANGE;

        $packet->entries = $entries;

        $player
            ->getNetworkSession()
            ->sendDataPacket($packet);
    }

    private function makeUniqueLine(
        string $line,
        int $index
    ): string {
        return $line . str_repeat(
                '§r',
                $index + 1
            );
    }

    private function removeScoreboard(
        Player $player
    ): void {
        $packet = new RemoveObjectivePacket();

        $packet->objectiveName =
            self::OBJECTIVE;

        $player
            ->getNetworkSession()
            ->sendDataPacket($packet);

        unset(
            $this->lines[
            $this->key($player)
            ]
        );
    }

    private function isAtServerSpawn(
        Player $player
    ): bool {
        $defaultWorld =
            $this->main
                ->getServer()
                ->getWorldManager()
                ->getDefaultWorld();

        if ($defaultWorld === null) {
            return false;
        }

        if ($player->getWorld() !== $defaultWorld) {
            return false;
        }

        $spawn =
            $defaultWorld->getSpawnLocation();

        return $player->getPosition()
                ->distanceSquared($spawn)
            <= (
                self::SPAWN_RADIUS *
                self::SPAWN_RADIUS
            );
    }

    private function key(Player $player): string
    {
        return strtolower(
            $player->getName()
        );
    }

    public function getMain(): Main
    {
        return $this->main;
    }

    public function getMiner(
        Player $player
    ): ?Miner {
        $manager =
            $this->main->getMinerManager();

        return $manager->getOrLoad(
            $player->getName()
        );
    }
}