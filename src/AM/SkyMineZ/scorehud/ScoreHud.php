<?php

declare(strict_types=1);

namespace AM\SkyMineZ\scorehud;

use AM\SkyMineZ\event\ScoreHudUpdateEvent;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\SpreadTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\scheduler\TaskHandler;

/**
 * The sidebar.
 *
 * Two layouts exist: a welcome screen while a player stands in the spawn area and
 * a stats screen everywhere else. Switching has hysteresis (see
 * `scoreboard.spawn-welcome-seconds` and `scoreboard.spawn-leave-seconds`) so
 * walking two blocks away does not make the board flicker.
 *
 * Performance notes, because this runs on every player:
 *
 *  - A pass over the online players is spread across several ticks with
 *    {@link SpreadTask}, which self-cancels once every entry was visited.
 *  - Only lines that actually changed are re-sent. An unchanged board costs one
 *    string comparison per line and zero packets.
 */
final class ScoreHud implements Listener
{
    private const OBJECTIVE = 'skymine';

    private const MODE_MIDDLE = 'middle';
    private const MODE_LOBBY = 'lobby';

    private MiddleLobbyScoreHud $middleHud;

    private LobbyScoreHud $lobbyHud;

    /** @var TaskHandler<ScoreHudTask>|null */
    private ?TaskHandler $task = null;

    /**
     * Current layout per player, keyed by lower-case name.
     *
     * @var array<string, string>
     */
    private array $modes = [];

    /**
     * Timestamp the player last entered the spawn area, or null.
     *
     * @var array<string, int|null>
     */
    private array $spawnEnteredAt = [];

    /**
     * Timestamp the player last left the spawn area, or null.
     *
     * @var array<string, int|null>
     */
    private array $leftSpawnAt = [];

    /**
     * The lines currently on each player's screen, so only changes are sent.
     *
     * @var array<string, list<string>>
     */
    private array $lines = [];

    /**
     * Players who switched the sidebar off with /hud.
     *
     * @var array<string, true>
     */
    private array $disabled = [];

    public function __construct(
        private Main $main
    ) {
        $this->middleHud = new MiddleLobbyScoreHud($main);
        $this->lobbyHud = new LobbyScoreHud($main);

        $this->start();
    }

    private function start(): void
    {
        $this->task?->cancel();

        $this->task = $this->main->getScheduler()->scheduleRepeatingTask(
            new ScoreHudTask($this),
            $this->getUpdateTicks()
        );
    }

    /**
     * Re-reads the config and re-arms the task with the new interval.
     */
    public function restart(): void
    {
        foreach (
            $this->lines as $name => $_
        ) {
            $player = $this->main->getServer()->getPlayerExact(
                $name
            );

            if ($player !== null) {
                $this->createScoreboard(
                    $player,
                    $this->titleFor($this->modes[$name] ?? self::MODE_LOBBY),
                    $this->linesFor(
                        $player,
                        $this->modes[$name] ?? self::MODE_LOBBY
                    )
                );
            }
        }

        $this->start();
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->initializePlayer($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $this->remove($event->getPlayer());
    }

    /**
     * One full pass over the online players, spread over several ticks.
     */
    public function tick(): void
    {
        SpreadTask::spread(
            $this->main,
            /*
             * No array_values() here: SpreadTask::flatten() already reindexes,
             * so one copy per pass is enough.
             */
            $this->main->getServer()
                ->getOnlinePlayers(),
            $this->getPlayersPerTick(),
            function(mixed $player): void {
                if ($player instanceof Player) {
                    $this->updatePlayer($player);
                }
            }
        );
    }

    public function initializePlayer(
        Player $player
    ): void {
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

            $this->render(
                $player,
                self::MODE_MIDDLE
            );

            return;
        }

        $this->modes[$name] = self::MODE_LOBBY;
        $this->leftSpawnAt[$name] = null;

        $this->render(
            $player,
            self::MODE_LOBBY
        );
    }

    public function remove(
        Player $player
    ): void {
        $name = $this->key($player);

        $this->removeScoreboard($player);

        unset(
            $this->modes[$name],
            $this->spawnEnteredAt[$name],
            $this->leftSpawnAt[$name],
            $this->lines[$name],
            $this->disabled[$name]
        );
    }

    /**
     * Flips the sidebar for one player.
     *
     * @return bool the new state, true meaning the sidebar is visible again
     */
    public function toggle(
        Player $player
    ): bool {
        $name = $this->key($player);

        if (isset($this->disabled[$name])) {
            unset(
                $this->disabled[$name],
                $this->modes[$name],
                $this->spawnEnteredAt[$name],
                $this->leftSpawnAt[$name]
            );

            $this->initializePlayer($player);

            return true;
        }

        $this->disabled[$name] = true;

        $this->removeScoreboard($player);

        unset(
            $this->modes[$name],
            $this->spawnEnteredAt[$name],
            $this->leftSpawnAt[$name]
        );

        return false;
    }

    public function isEnabledFor(
        Player $player
    ): bool {
        return !isset(
            $this->disabled[$this->key($player)]
        );
    }

    public function updatePlayer(
        Player $player
    ): void {
        if (!$player->isConnected()) {
            return;
        }

        $name = $this->key($player);

        if (isset($this->disabled[$name])) {
            /*
             * Turned off after the board was created: send the removal once and
             * stop touching this player.
             */
            if (isset($this->lines[$name])) {
                $this->removeScoreboard($player);

                unset($this->modes[$name]);
            }

            return;
        }

        if (!isset($this->modes[$name])) {
            $this->initializePlayer($player);

            return;
        }

        $now = time();
        $mode = $this->modes[$name];

        if ($this->isAtServerSpawn($player)) {
            $this->leftSpawnAt[$name] = null;

            if ($mode !== self::MODE_MIDDLE) {
                $this->modes[$name] = self::MODE_MIDDLE;
                $this->spawnEnteredAt[$name] = $now;
            }

            $enteredAt = $this->spawnEnteredAt[$name] ?? $now;

            /*
             * The welcome screen only turns into the stats screen after the
             * player has stayed in spawn for a while, so spawning and dying in the
             * spawn area does not make the board change constantly.
             */
            $mode = ($now - $enteredAt) >= $this->getWelcomeSeconds()
                ? self::MODE_LOBBY
                : self::MODE_MIDDLE;

            $this->modes[$name] = $mode;

            $this->render(
                $player,
                $mode
            );

            return;
        }

        if ($mode === self::MODE_MIDDLE) {
            if ($this->leftSpawnAt[$name] === null) {
                $this->leftSpawnAt[$name] = $now;
            }

            /*
             * Same idea in reverse: stepping two blocks out of the radius does
             * not immediately swap the board.
             */
            if (($now - $this->leftSpawnAt[$name]) >= $this->getLeaveSeconds()) {
                $this->modes[$name] = self::MODE_LOBBY;
                $this->spawnEnteredAt[$name] = 0;
            }

            $mode = $this->modes[$name];
        }

        $this->render(
            $player,
            $mode
        );
    }

    /**
     * Sends the board if it differs from what the player already sees.
     */
    private function render(
        Player $player,
        string $mode
    ): void {
        $name = $this->key($player);
        $title = $this->titleFor($mode);
        $lines = $this->linesFor(
            $player,
            $mode
        );

        if (ScoreHudUpdateEvent::hasHandlers()) {
            $event = new ScoreHudUpdateEvent(
                $name,
                $title,
                $lines
            );

            $event->call();

            if ($event->isCancelled()) {
                return;
            }
        }

        $oldLines = $this->lines[$name] ?? null;

        if ($oldLines === null) {
            $this->createScoreboard(
                $player,
                $title,
                $lines
            );

            return;
        }

        if ($oldLines === $lines) {
            return;
        }

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

        $player->getNetworkSession()->sendDataPacket(
            $packet
        );

        $this->sendChangedLines(
            $player,
            [],
            $lines
        );

        $this->lines[$this->key($player)] = $lines;
    }

    /**
     * Sends only the lines whose text or position changed, plus removals for
     * lines that disappeared.
     *
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

        for (
            $index = 0;
            $index < $max;
            ++$index
        ) {
            $old = $oldLines[$index] ?? null;
            $new = $newLines[$index] ?? null;

            if ($old === $new) {
                continue;
            }

            /*
             * Bedrock identifies a scoreboard row by its id, not by its text, so
             * a line has to be removed by sending the *old* text with the same
             * id. That is what the padding below is for: it makes the text unique
             * per row, which also stops the client from merging two rows that
             * happen to read the same.
             */
            $entry = new ScorePacketEntry();

            $entry->objectiveName = self::OBJECTIVE;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
            $entry->customName = $this->makeUniqueLine(
                $new ?? (string) $old,
                $index
            );

            $entry->score = count($newLines) - $index;

            $entry->scoreboardId = $index + 1;

            $entries[] = $entry;
        }

        if ($entries === []) {
            return;
        }

        $packet = new SetScorePacket();
        $packet->type = SetScorePacket::TYPE_CHANGE;
        $packet->entries = $entries;

        $player->getNetworkSession()->sendDataPacket(
            $packet
        );
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
        $packet->objectiveName = self::OBJECTIVE;

        $player->getNetworkSession()->sendDataPacket(
            $packet
        );

        unset(
            $this->lines[$this->key($player)]
        );
    }

    private function titleFor(
        string $mode
    ): string {
        return $mode === self::MODE_MIDDLE
            ? $this->middleHud->getTitle()
            : $this->lobbyHud->getTitle();
    }

    /**
     * @return list<string>
     */
    private function linesFor(
        Player $player,
        string $mode
    ): array {
        return $mode === self::MODE_MIDDLE
            ? $this->middleHud->getLines()
            : $this->lobbyHud->getLines($player);
    }

    private function isAtServerSpawn(
        Player $player
    ): bool {
        $defaultWorld = $this->main
            ->getServer()
            ->getWorldManager()
            ->getDefaultWorld();

        if ($defaultWorld === null) {
            return false;
        }

        if ($player->getWorld() !== $defaultWorld) {
            return false;
        }

        $radius = $this->getSpawnRadius();

        return $player->getPosition()->distanceSquared(
            $defaultWorld->getSpawnLocation()
        ) <= ($radius * $radius);
    }

    private function key(
        Player $player
    ): string {
        return strtolower(
            $player->getName()
        );
    }

    private function getWelcomeSeconds(): int
    {
        return max(
            0,
            $this->main
                ->getConfigManager()
                ->getInt(
                    'scoreboard.spawn-welcome-seconds',
                    60
                )
        );
    }

    private function getLeaveSeconds(): int
    {
        return max(
            0,
            $this->main
                ->getConfigManager()
                ->getInt(
                    'scoreboard.spawn-leave-seconds',
                    5
                )
        );
    }

    private function getSpawnRadius(): float
    {
        return $this->main
            ->getConfigManager()
            ->getFloat(
                'scoreboard.spawn-radius',
                3.0
            );
    }

    private function getPlayersPerTick(): int
    {
        return max(
            1,
            $this->main
                ->getConfigManager()
                ->getInt(
                    'scoreboard.players-per-tick',
                    4
                )
        );
    }

    private function getUpdateTicks(): int
    {
        return max(
            1,
            $this->main
                ->getConfigManager()
                ->getInt(
                    'scoreboard.update-ticks',
                    20
                )
        );
    }
}