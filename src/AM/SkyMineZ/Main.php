<?php

declare(strict_types=1);

namespace AM\SkyMineZ;

use AM\SkyMineZ\command\CommandRegistry;
use AM\SkyMineZ\command\SelectionManager;
use AM\SkyMineZ\config\ConfigManager;
use AM\SkyMineZ\crate\CrateListener;
use AM\SkyMineZ\crate\CrateManager;
use AM\SkyMineZ\economy\EconomyListener;
use AM\SkyMineZ\economy\GoldEconomy;
use AM\SkyMineZ\economy\MoneyEconomy;
use AM\SkyMineZ\lagmaker\LagMaker;
use AM\SkyMineZ\leaderboard\LeaderboardListener;
use AM\SkyMineZ\leaderboard\LeaderboardManager;
use AM\SkyMineZ\mine\MineListener;
use AM\SkyMineZ\mine\MineManager;
use AM\SkyMineZ\miner\MinerListener;
use AM\SkyMineZ\miner\MinerManager;
use AM\SkyMineZ\outpost\OutpostListener;
use AM\SkyMineZ\outpost\OutpostManager;
use AM\SkyMineZ\pvp\PvpListener;
use AM\SkyMineZ\pvp\PvpManager;
use AM\SkyMineZ\scorehud\ScoreHud;
use AM\SkyMineZ\slapper\SlapperListener;
use AM\SkyMineZ\slapper\SlapperManager;
use JsonException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\WorldManager;

/**
 * Plugin entry point.
 *
 * Startup order matters and is deliberate:
 *
 *  1. Config and the raw JSON stores are opened in onLoad, because managers
 *     resolve their world from disk the moment they are constructed.
 *  2. Listeners are registered *before* the data is loaded, so a player who joins
 *     while a big world is still loading cannot slip past a protection.
 *  3. The data is loaded last, because it spawns entities and holograms.
 *  4. onDisable saves everything, guarded, because PM calls it even when
 *     onEnable threw.
 */
final class Main extends PluginBase
{
    public const PLUGIN_NAME = 'SkyMineZ';

    /**
     * Player-facing permission: the menu, the PvP and sidebar toggles, stats and
     * the pos1/pos2 markers.
     */
    public const PERMISSION_USE = 'skyminez.use';

    /**
     * Everything that creates or deletes something on the server.
     */
    public const PERMISSION_ADMIN = 'skyminez.admin';

    private static self $instance;

    private Config $cratesDB;
    private Config $pvpDB;
    private Config $minerDB;

    private ConfigManager $configManager;

    private MoneyEconomy $moneyEconomy;
    private GoldEconomy $goldEconomy;

    private CrateManager $crateManager;
    private PvpManager $pvpManager;
    private MinerManager $minerManager;
    private SlapperManager $slapperManager;
    private LeaderboardManager $leaderboardManager;
    private MineManager $mineManager;
    private OutpostManager $outpostManager;
    private LagMaker $lagMaker;
    private ScoreHud $scoreHud;

    private SelectionManager $selectionManager;

    protected function onLoad(): void
    {
        self::$instance = $this;

        $dataFolder = $this->getDataFolder();

        if (!is_dir($dataFolder)) {
            @mkdir(
                $dataFolder,
                0777,
                true
            );
        }

        $this->configManager = new ConfigManager($this);
        $this->selectionManager = new SelectionManager();

        $this->cratesDB = new Config(
            $dataFolder . 'crates.json',
            Config::JSON
        );

        $this->pvpDB = new Config(
            $dataFolder . 'pvp.json',
            Config::JSON
        );

        $this->minerDB = new Config(
            $dataFolder . 'miner.json',
            Config::JSON
        );
    }

    protected function onEnable(): void
    {
        $config = $this->getConfigManager();

        $this->moneyEconomy = new MoneyEconomy(
            new Config(
                $this->getDataFolder() . 'money_economy.json',
                Config::JSON
            ),
            $config->getInt('economy.money-default', 0)
        );

        $this->goldEconomy = new GoldEconomy(
            new Config(
                $this->getDataFolder() . 'gold_economy.json',
                Config::JSON
            ),
            $config->getInt('economy.gold-default', 0)
        );

        $this->pvpManager = new PvpManager($this);
        $this->minerManager = new MinerManager($this);
        $this->crateManager = new CrateManager($this);
        $this->slapperManager = new SlapperManager($this);
        $this->leaderboardManager = new LeaderboardManager($this);
        $this->mineManager = new MineManager($this);
        $this->outpostManager = new OutpostManager(
            $this,
            $this->goldEconomy
        );

        $this->registerListeners();

        /*
         * Commands are registered before the data is loaded so a player who
         * somehow runs a command during startup gets a clean "not loaded yet"
         * instead of a crash.
         */
        CommandRegistry::registerAll($this);

        $this->loadData();

        $this->getLogger()->info(
            sprintf(
                'Loaded %d crate(s), %d mine(s), %d outpost(s), %d leaderboard(s), %d slapper(s).',
                $this->crateManager->count(),
                $this->mineManager->count(),
                $this->outpostManager->count(),
                $this->leaderboardManager->count(),
                count($this->slapperManager->getSlappers())
            )
        );
    }

    protected function onDisable(): void
    {
        /*
         * onDisable also runs when onEnable failed halfway through, so every
         * step is guarded with isset(): the typed properties below throw Error
         * (not null) when uninitialized, which ?-> would not catch. A
         * JsonException during shutdown must not mask the original failure.
         */
        try {
            if (isset($this->crateManager)) {
                $this->crateManager->saveAll();
            }

            if (isset($this->pvpManager)) {
                $this->pvpManager->saveAll();
            }

            if (isset($this->minerManager)) {
                $this->minerManager->saveAll();
            }

            if (isset($this->moneyEconomy)) {
                $this->moneyEconomy->saveAll();
            }

            if (isset($this->goldEconomy)) {
                $this->goldEconomy->saveAll();
            }

            if (isset($this->slapperManager)) {
                $this->slapperManager->saveAll();
            }

            if (isset($this->leaderboardManager)) {
                $this->leaderboardManager->saveAll();
            }

            if (isset($this->mineManager)) {
                $this->mineManager->saveAll();
            }

            if (isset($this->outpostManager)) {
                $this->outpostManager->saveAll();
            }

            if (isset($this->lagMaker)) {
                $this->lagMaker->stop();
            }
        } catch (JsonException $exception) {
            $this->getLogger()->error(
                'Failed to save SkyMineZ data: ' . $exception->getMessage()
            );
        }
    }

    /**
     * Re-reads config.yml and re-applies everything that reads from it.
     */
    public function reload(): void
    {
        $this->getConfigManager()->reload();

        $this->crateManager->load();
        $this->slapperManager->load();
        $this->leaderboardManager->load();
        $this->mineManager->load();
        $this->outpostManager->load();

        $this->moneyEconomy->setDefaultBalance(
            $this->getConfigManager()->getInt(
                'economy.money-default',
                0
            )
        );

        $this->goldEconomy->setDefaultBalance(
            $this->getConfigManager()->getInt(
                'economy.gold-default',
                0
            )
        );

        $this->scoreHud->restart();
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getConfigManager(): ConfigManager
    {
        return $this->configManager;
    }

    /**
     * Per-player pos1/pos2 selection shared by the cuboid commands.
     */
    public function getSelectionManager(): SelectionManager
    {
        return $this->selectionManager;
    }

    public function getCrateDB(): Config
    {
        return $this->cratesDB;
    }

    public function getPvpDB(): Config
    {
        return $this->pvpDB;
    }

    public function getMinerDB(): Config
    {
        return $this->minerDB;
    }

    public function getWorldManager(): WorldManager
    {
        return $this->getServer()
            ->getWorldManager();
    }

    public function getCrateManager(): CrateManager
    {
        return $this->crateManager;
    }

    public function getMoneyEconomy(): MoneyEconomy
    {
        return $this->moneyEconomy;
    }

    public function getGoldEconomy(): GoldEconomy
    {
        return $this->goldEconomy;
    }

    public function getPvpManager(): PvpManager
    {
        return $this->pvpManager;
    }

    public function getMinerManager(): MinerManager
    {
        return $this->minerManager;
    }

    public function getSlapperManager(): SlapperManager
    {
        return $this->slapperManager;
    }

    public function getLeaderboardManager(): LeaderboardManager
    {
        return $this->leaderboardManager;
    }

    public function getMineManager(): MineManager
    {
        return $this->mineManager;
    }

    public function getOutpostManager(): OutpostManager
    {
        return $this->outpostManager;
    }

    public function getScoreHud(): ScoreHud
    {
        return $this->scoreHud;
    }

    public function getLagMaker(): LagMaker
    {
        return $this->lagMaker;
    }

    private function registerListeners(): void
    {
        $pluginManager = $this->getServer()
            ->getPluginManager();

        // The sidebar registers itself as a listener, so build it first.
        $this->scoreHud = new ScoreHud($this);

        /*
         * Same-priority handlers run in registration order, so the protections
         * that cancel block breaks (crate, mine, slapper) are registered before
         * MinerListener: otherwise a break that gets cancelled would still be
         * counted towards the MINED stat.
         */
        foreach (
            [
                new CrateListener(
                    $this->crateManager,
                    $this
                ),
                new MineListener($this),
                new SlapperListener(
                    $this->slapperManager,
                    $this
                ),
                new EconomyListener($this),
                new PvpListener($this),
                new MinerListener($this),
                new OutpostListener($this),
                new LeaderboardListener($this),
                $this->scoreHud
            ] as $listener
        ) {
            $pluginManager->registerEvents(
                $listener,
                $this
            );
        }

        $this->lagMaker = new LagMaker($this);
    }

    private function loadData(): void
    {
        $this->crateManager->load();
        $this->slapperManager->load();
        $this->leaderboardManager->load();
        $this->mineManager->load();
        $this->outpostManager->load();

        /*
         * A mine restored from disk has not been rebuilt this session yet, so it
         * starts empty. Refilling now means a restart never leaves a dead mine.
         */
        $this->mineManager->fillRestored();

        $this->showToOnlinePlayers();
    }

    /**
     * Pushes every hologram to the players already online, which happens on
     * /skymine reload and on a plugin hot-restart.
     */
    private function showToOnlinePlayers(): void
    {
        foreach (
            $this->getServer()->getOnlinePlayers() as $player
        ) {
            $this->scoreHud->initializePlayer($player);
            $this->crateManager->spawnTo($player);
            $this->mineManager->spawnTo($player);
            $this->outpostManager->spawnTo($player);
            $this->leaderboardManager->spawnTo($player);
            $this->slapperManager->spawnTo($player);
        }
    }
}