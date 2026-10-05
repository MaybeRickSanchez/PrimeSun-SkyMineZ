<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\economy\EconomyChangeEventReason;
use AM\SkyMineZ\economy\GoldEconomy;
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
 * Owns every outpost, drives their capture logic and pays out the owner's gold.
 *
 * Outposts live in plugin_data/outposts.json and are restored with their owner,
 * state and timers, so a restart does not hand a captured outpost back to
 * nobody.
 */
final class OutpostManager
{
    private const FILE_NAME = 'outposts.json';

    /** @var array<string, Outpost> */
    private array $outposts = [];

    private Config $db;

    /** @var TaskHandler<OutpostTask>|null */
    private ?TaskHandler $task = null;

    public function __construct(
        private Main $main,
        private GoldEconomy $goldEconomy
    ) {
        $this->db = new Config(
            $this->main->getDataFolder() . self::FILE_NAME,
            Config::JSON
        );
    }

    public function load(): void
    {
        $this->despawnAll();

        $this->outposts = [];

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

            $outpost = $this->createFromArray(
                $name,
                $data,
                $worldManager
            );

            if ($outpost === null) {
                $this->main->getLogger()->warning(
                    "Skipped malformed outpost '{$name}' in " . self::FILE_NAME
                );

                continue;
            }

            $this->outposts[$name] = $outpost;

            $outpost->spawn();
        }

        $this->startTask();
    }

    public function saveAll(): void
    {
        $data = [];

        foreach (
            $this->outposts as $name => $outpost
        ) {
            $data[$name] = $outpost->toArray();
        }

        $this->db->setAll($data);
        $this->db->save();
    }

    public function save(
        string $name
    ): void {
        $outpost = $this->outposts[$name] ?? null;

        if ($outpost === null) {
            return;
        }

        $this->db->set(
            $name,
            $outpost->toArray()
        );

        $this->db->save();
    }

    /**
     * @throws RuntimeException when the name is taken or the positions differ
     */
    public function create(
        string $name,
        Position $pos1,
        Position $pos2
    ): Outpost {
        if ($this->has($name)) {
            throw new RuntimeException(
                "Outpost '$name' already exists."
            );
        }

        if ($pos1->getWorld() !== $pos2->getWorld()) {
            throw new RuntimeException(
                'Both positions must be in the same world.'
            );
        }

        $config = $this->main->getConfigManager();

        $outpost = new Outpost(
            $name,
            $pos1,
            $pos2,
            $pos1->getWorld(),
            $config->getInt('outposts.capture-required', 100),
            $config->getInt('outposts.cooldown-duration', 1800),
            $config->getInt('outposts.gold-interval', 600),
            $config->getInt('outposts.gold-chance', 50),
            $config->getInt('outposts.gold-reward', 1)
        );

        $this->outposts[$name] = $outpost;

        $outpost->spawn();

        $this->save($name);

        return $outpost;
    }

    public function remove(
        string $name
    ): bool {
        $outpost = $this->outposts[$name] ?? null;

        if ($outpost === null) {
            return false;
        }

        $outpost->deSpawn();

        unset($this->outposts[$name]);

        $this->db->remove($name);
        $this->db->save();

        return true;
    }

    public function has(
        string $name
    ): bool {
        return isset($this->outposts[$name]);
    }

    public function get(
        string $name
    ): ?Outpost {
        return $this->outposts[$name] ?? null;
    }

    /**
     * @return array<string, Outpost>
     */
    public function getAll(): array
    {
        return $this->outposts;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->outposts);
    }

    public function count(): int
    {
        return count($this->outposts);
    }

    /**
     * The outpost a player is standing in, or null.
     */
    public function getOutpostAt(
        World $world,
        Vector3 $position
    ): ?Outpost {
        foreach (
            $this->outposts as $outpost
        ) {
            if (
                $outpost->getWorld() !== $world
            ) {
                continue;
            }

            if ($outpost->isIn($position)) {
                return $outpost;
            }
        }

        return null;
    }

    /**
     * Outposts a player currently owns.
     *
     * @return list<string>
     */
    public function getOwnedBy(
        string $playerName
    ): array {
        $result = [];

        foreach (
            $this->outposts as $name => $outpost
        ) {
            if (
                $outpost->getOwner() !== null
                && strcasecmp(
                    $outpost->getOwner(),
                    $playerName
                ) === 0
            ) {
                $result[] = (string) $name;
            }
        }

        return $result;
    }

    public function tickAll(): void
    {
        $now = time();

        $config = $this->main->getConfigManager();

        $captureMin = $config->getInt('outposts.capture-min', 1);
        $captureMax = $config->getInt('outposts.capture-max', 3);

        foreach (
            $this->outposts as $name => $outpost
        ) {
            $previousOwner = $outpost->getOwner();
            $previousState = $outpost->getState();

            $outpost->tick(
                $now,
                $captureMin,
                $captureMax
            );

            if ($outpost->getState() !== $previousState) {
                $this->announceStateChange(
                    $outpost,
                    $previousState
                );
            }

            if ($outpost->getOwner() !== $previousOwner) {
                $this->announceOwnerChange(
                    $outpost,
                    $previousOwner
                );

                $this->save((string) $name);
            }

            $this->handleGold($outpost, $now);
        }
    }

    public function despawnAll(): void
    {
        foreach (
            $this->outposts as $outpost
        ) {
            $outpost->deSpawn();
        }
    }

    public function getDatabase(): Config
    {
        return $this->db;
    }

    /**
     * Shows the outpost holograms to a player who just spawned in.
     */
    public function spawnTo(
        Player $player
    ): void {
        SpreadTask::spread(
            $this->main,
            $this->outposts,
            2,
            function(
                mixed $outpost
            ) use ($player): void {
                if (
                    !$outpost instanceof Outpost
                    || !$outpost->getInfo()->isSpawned()
                    || $outpost->getWorld() !== $player->getWorld()
                ) {
                    return;
                }

                $outpost->getInfo()
                    ->getParticle()
                    ?->spawn($player);
            }
        );
    }

    private function startTask(): void
    {
        $this->task?->cancel();

        $interval = max(
            1,
            $this->main
                ->getConfigManager()
                ->getInt('outposts.tick-interval', 20)
        );

        $this->task = $this->main->getScheduler()->scheduleRepeatingTask(
            new OutpostTask($this, $interval),
            $interval
        );
    }

    private function announceStateChange(
        Outpost $outpost,
        string $previousState
    ): void {
        if (
            $previousState !== Outpost::STATE_COOLDOWN
            || $outpost->getState() !== Outpost::STATE_CAPTABLE
        ) {
            return;
        }

        $this->main->getServer()->broadcastMessage(
            "§a+ §eThe outpost §6" . $outpost->getName()
            . " §ais capturable again!"
        );
    }

    private function announceOwnerChange(
        Outpost $outpost,
        ?string $previousOwner
    ): void {
        $owner = $outpost->getOwner();

        if ($owner === null) {
            return;
        }

        if ($previousOwner === null) {
            $this->main->getServer()->broadcastMessage(
                "§6+ §e" . $owner . " §6has captured the outpost §e"
                . $outpost->getName() . "§6!"
            );

            return;
        }

        $this->main->getServer()->broadcastMessage(
            "§6+ §e" . $owner . " §6has taken the outpost §e"
            . $outpost->getName() . " §6from §e"
            . $previousOwner . "§6!"
        );
    }

    private function handleGold(
        Outpost $outpost,
        int $now
    ): void {
        $owner = $outpost->getOwner();

        if (
            $owner === null
            || !$outpost->isGoldDue($now)
        ) {
            return;
        }

        $reward = $outpost->getGoldReward();

        if ($reward <= 0) {
            return;
        }

        $this->goldEconomy->add(
            $owner,
            $reward,
            EconomyChangeEventReason::OUTPOST
        );

        $player = $this->main->getServer()->getPlayerExact(
            $owner
        );

        $player?->sendMessage(
            "§6+ You received §e" . $reward
            . " §6gold from the outpost §e"
            . $outpost->getName() . "§6!"
        );
    }

    /**
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
    ): ?Outpost {
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
            !self::isVectorTriple($pos1)
            || !self::isVectorTriple($pos2)
        ) {
            return null;
        }

        $world = $this->resolveWorld(
            $data['world'],
            $worldManager
        );

        if ($world === null) {
            return null;
        }

        $config = $this->main->getConfigManager();

        $outpost = new Outpost(
            $name,
            new Vector3(
                $pos1[0],
                $pos1[1],
                $pos1[2]
            ),
            new Vector3(
                $pos2[0],
                $pos2[1],
                $pos2[2]
            ),
            $world,
            $config->getInt('outposts.capture-required', 100),
            $config->getInt('outposts.cooldown-duration', 1800),
            $config->getInt('outposts.gold-interval', 600),
            $config->getInt('outposts.gold-chance', 50),
            $config->getInt('outposts.gold-reward', 1)
        );

        $owner = $data['owner'] ?? null;
        $state = $data['state'] ?? Outpost::STATE_CAPTABLE;

        $outpost->restore(
            is_string($owner) ? $owner : null,
            is_string($state) ? $state : Outpost::STATE_CAPTABLE,
            isset($data['progress']) && is_numeric($data['progress'])
                ? (int) $data['progress']
                : 0,
            isset($data['availableAt']) && is_numeric($data['availableAt'])
                ? (int) $data['availableAt']
                : 0,
            isset($data['lastGoldAt']) && is_numeric($data['lastGoldAt'])
                ? (int) $data['lastGoldAt']
                : 0
        );

        return $outpost;
    }

    /**
     * @param mixed $value
     *
     * @phpstan-assert-if-true array{float, float, float} $value
     */
    private static function isVectorTriple(
        mixed $value
    ): bool {
        return is_array($value)
            && isset($value[0], $value[1], $value[2])
            && is_numeric($value[0])
            && is_numeric($value[1])
            && is_numeric($value[2]);
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