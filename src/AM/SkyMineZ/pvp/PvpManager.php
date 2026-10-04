<?php

declare(strict_types=1);

namespace AM\SkyMineZ\pvp;

use AM\SkyMineZ\Main;

final class PvpManager
{
    private Main $main;

    /**
     * @var array<string, bool>
     */
    private array $players = [];

    public function __construct()
    {
        $this->main = Main::getInstance();
    }

    public function loadPlayer(string $playerName): void
    {
        $playerName = strtolower($playerName);

        $this->players[$playerName] = (bool) $this->main
            ->getPvpDB()
            ->get($playerName, true);
    }

    public function savePlayer(string $playerName): void
    {
        $playerName = strtolower($playerName);

        if (!isset($this->players[$playerName])) {
            return;
        }

        $this->main
            ->getPvpDB()
            ->set(
                $playerName,
                $this->players[$playerName]
            );
    }

    public function setPlayer(
        string $playerName,
        bool $pvp = true
    ): void {
        $playerName = strtolower($playerName);

        $this->players[$playerName] = $pvp;
    }

    public function getPlayerState(string $playerName): bool
    {
        $playerName = strtolower($playerName);

        return $this->players[$playerName] ?? true;
    }

    public function isLoaded(string $playerName): bool
    {
        return isset(
            $this->players[strtolower($playerName)]
        );
    }

    public function unloadPlayer(string $playerName): void
    {
        unset(
            $this->players[strtolower($playerName)]
        );
    }

    public function saveAll(): void
    {
        $db = $this->main->getPvpDB();

        foreach ($this->players as $playerName => $pvp) {
            $db->set(
                $playerName,
                $pvp
            );
        }

        $db->save();
    }
}