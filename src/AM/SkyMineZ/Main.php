<?php

declare(strict_types=1);

namespace AM\SkyMineZ;

use AM\SkyMineZ\crate\CrateListener;
use AM\SkyMineZ\crate\CrateManager;
use AM\SkyMineZ\economy\EconomyListener;
use AM\SkyMineZ\economy\GoldEconomy;
use AM\SkyMineZ\economy\MoneyEconomy;
use AM\SkyMineZ\leaderboard\LeaderboardManager;
use AM\SkyMineZ\miner\MinerListener;
use AM\SkyMineZ\miner\MinerManager;
use AM\SkyMineZ\pvp\PvpListener;
use AM\SkyMineZ\pvp\PvpManager;
use AM\SkyMineZ\scorehud\ScoreHud;
use AM\SkyMineZ\slapper\SlapperListener;
use AM\SkyMineZ\slapper\SlapperManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase
{
    private static self $instance;

    private Config $cratesDB;
    private Config $pvpDB;
    private Config $minerDB;

    private MoneyEconomy $moneyEconomy;
    private GoldEconomy $goldEconomy;

    private CrateManager $crateManager;
    private PvpManager $pvpManager;
    private MinerManager $minerManager;
    private SlapperManager $slapperManager;
    private LeaderboardManager $leaderboardManager;

    private ScoreHud $scoreHud;

    protected function onLoad(): void
    {
        self::$instance = $this;

        @mkdir(
            $this->getDataFolder()
        );

        $this->cratesDB = new Config(
            $this->getDataFolder() .
            'crates.json',
            Config::JSON
        );

        $this->pvpDB = new Config(
            $this->getDataFolder() .
            'pvp.json',
            Config::JSON
        );

        $this->minerDB = new Config(
            $this->getDataFolder() .
            'miner.json',
            Config::JSON
        );
    }

    protected function onEnable(): void
    {
        $this->moneyEconomy =
            new MoneyEconomy(
                new Config(
                    $this->getDataFolder() .
                    'money_economy.json',
                    Config::JSON
                )
            );

        $this->goldEconomy =
            new GoldEconomy(
                new Config(
                    $this->getDataFolder() .
                    'gold_economy.json',
                    Config::JSON
                )
            );

        $this->crateManager =
            new CrateManager(
                $this
            );

        $this->pvpManager =
            new PvpManager();

        $this->minerManager =
            new MinerManager();

        $this->slapperManager =
            new SlapperManager(
                $this
            );

        $this->leaderboardManager =
            new LeaderboardManager(
                $this
            );

        $pluginManager =
            $this->getServer()
                ->getPluginManager();

        $pluginManager->registerEvents(
            new CrateListener(
                $this->crateManager
            ),
            $this
        );

        $pluginManager->registerEvents(
            new EconomyListener(
                $this->moneyEconomy,
                $this->goldEconomy
            ),
            $this
        );

        $pluginManager->registerEvents(
            new PvpListener(),
            $this
        );
        $pluginManager->registerEvents(new MinerListener($this), $this);
        $pluginManager->registerEvents(
            new SlapperListener(
                $this->slapperManager
            ),
            $this
        );

        $this->crateManager->load();

        $this->slapperManager->load();

        $this->leaderboardManager->load();

        $this->scoreHud =
            new ScoreHud(
                $this
            );

        $pluginManager->registerEvents(
            $this->scoreHud,
            $this
        );
    }

    protected function onDisable(): void
    {
        if (isset($this->crateManager)) {
            $this->crateManager->saveAll();
        }

        if (isset($this->minerManager)) {
            $this->minerManager->saveAll();
        }

        if (isset($this->pvpManager)) {
            $this->pvpManager->saveAll();
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

        $this->cratesDB->save();
        $this->pvpDB->save();
        $this->minerDB->save();
    }

    public static function getInstance(): self
    {
        return self::$instance;
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

    public function getScoreHud(): ScoreHud
    {
        return $this->scoreHud;
    }
}