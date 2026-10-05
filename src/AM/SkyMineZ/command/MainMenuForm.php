<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\form\FormAPI;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\player\Player;

/**
 * The `/skymine menu` window.
 *
 * It is built with the bundled {@link FormAPI} port, which is the same code path
 * other plugins can use: `FormAPI::simple()`, a closure, chained setters and one
 * `send()` call.
 *
 * Player-only entries are added based on the player's own state, so the menu
 * shows "Enable PvP" instead of "Disable PvP" and never offers an admin action
 * to somebody who cannot use it.
 */
final class MainMenuForm
{
    private const LABEL_PVP = 'pvp';
    private const LABEL_SIDEBAR = 'sidebar';
    private const LABEL_STATS = 'stats';
    private const LABEL_RELOAD = 'reload';
    private const LABEL_SAVE = 'save';
    private const LABEL_CLOSE = 'close';

    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $pvpEnabled = $this->plugin->getPvpManager()->getState(
            $player->getName()
        );

        $sidebarEnabled = $this->plugin->getScoreHud()->isEnabledFor(
            $player
        );

        $isAdmin = $player->hasPermission(
            Main::PERMISSION_ADMIN
        );

        $form = FormAPI::simple(
            function(
                Player $who,
                mixed $data
            ): void {
                $this->handle($who, $data);
            }
        );

        $form
            ->setTitle($this->plugin->getConfigManager()->getPrefix()
                . 'Menu')
            ->setContent($this->describe(
                $player,
                $pvpEnabled
            ))
            ->addButton(
                ($pvpEnabled ? '§aPvP: ON' : '§cPvP: OFF')
                . ' §8- §7click to change',
                -1,
                '',
                self::LABEL_PVP
            )
            ->addButton(
                ($sidebarEnabled ? '§aSidebar: ON' : '§cSidebar: OFF')
                . ' §8- §7click to change',
                -1,
                '',
                self::LABEL_SIDEBAR
            )
            ->addButton(
                '§eMy stats',
                -1,
                '',
                self::LABEL_STATS
            );

        if ($isAdmin) {
            $form->addButton(
                '§6Reload plugin',
                -1,
                '',
                self::LABEL_RELOAD
            );

            $form->addButton(
                '§6Save everything',
                -1,
                '',
                self::LABEL_SAVE
            );
        }

        $form->addButton(
            '§cClose',
            -1,
            '',
            self::LABEL_CLOSE
        );

        return FormAPI::send(
            $form,
            $player
        );
    }

    private function handle(
        Player $player,
        mixed $data
    ): void {
        if (!is_string($data)) {
            return;
        }

        switch ($data) {
            case self::LABEL_PVP:
                $state = $this->plugin->getPvpManager()->toggle(
                    $player->getName()
                );

                $player->sendMessage(
                    $this->prefix() . ($state ? '§aPvP enabled.' : '§cPvP disabled.')
                );

                /*
                 * Reopen so the menu reflects the new state straight away
                 * instead of the player having to run the command again.
                 */
                $this->send($player);
                break;

            case self::LABEL_SIDEBAR:
                $enabled = $this->plugin->getScoreHud()->toggle(
                    $player
                );

                $player->sendMessage(
                    $this->prefix() . ($enabled
                        ? '§aSidebar enabled.'
                        : '§cSidebar disabled.')
                );

                $this->send($player);
                break;

            case self::LABEL_STATS:
                $miner = $this->plugin->getMinerManager()->getOrLoad(
                    $player->getName()
                );

                $player->sendMessage(
                    $this->prefix() . '§eYour stats:'
                );
                $player->sendMessage(
                    $this->prefix() . '§7Mined: §f'
                    . NumberFormatter::short($miner->getMined())
                    . ' §8| §7Deaths: §f'
                    . NumberFormatter::short($miner->getDeaths())
                );
                $player->sendMessage(
                    $this->prefix() . '§7Kills: §f'
                    . NumberFormatter::short($miner->getKills())
                    . ' §8| §7Kill streak: §f'
                    . $miner->getKillStreak()
                );
                $player->sendMessage(
                    $this->prefix() . '§7Money: §f'
                    . NumberFormatter::short(
                        $this->plugin->getMoneyEconomy()->get(
                            $player->getName()
                        )
                    )
                    . ' §8| §7Gold: §f'
                    . NumberFormatter::short(
                        $this->plugin->getGoldEconomy()->get(
                            $player->getName()
                        )
                    )
                );

                $this->send($player);
                break;

            case self::LABEL_RELOAD:
                if (!$player->hasPermission(Main::PERMISSION_ADMIN)) {
                    $player->sendMessage(
                        $this->prefix() . '§cYou cannot do that.'
                    );

                    break;
                }

                try {
                    $this->plugin->reload();
                } catch (\Throwable $exception) {
                    $player->sendMessage(
                        $this->prefix() . '§cReload failed: '
                        . $exception->getMessage()
                    );

                    $this->plugin->getLogger()->logException(
                        $exception
                    );

                    break;
                }

                $player->sendMessage(
                    $this->prefix() . '§aPlugin reloaded.'
                );
                break;

            case self::LABEL_SAVE:
                if (!$player->hasPermission(Main::PERMISSION_ADMIN)) {
                    $player->sendMessage(
                        $this->prefix() . '§cYou cannot do that.'
                    );

                    break;
                }

                try {
                    $this->plugin->getCrateManager()->saveAll();
                    $this->plugin->getSlapperManager()->saveAll();
                    $this->plugin->getLeaderboardManager()->saveAll();
                    $this->plugin->getMineManager()->saveAll();
                    $this->plugin->getOutpostManager()->saveAll();
                } catch (\Throwable $exception) {
                    $player->sendMessage(
                        $this->prefix() . '§cSave failed: '
                        . $exception->getMessage()
                    );

                    $this->plugin->getLogger()->logException(
                        $exception
                    );

                    break;
                }

                $player->sendMessage(
                    $this->prefix() . '§aEverything saved.'
                );
                break;
        }
    }

    private function describe(
        Player $player,
        bool $pvpEnabled
    ): string {
        $lines = [
            '§7Welcome, §f' . $player->getName() . '§7.',
            '§8' . str_repeat(
                '-',
                24
            ),
            '§7PvP: §f' . ($pvpEnabled ? 'ON' : 'OFF'),
            '§7Server: §f'
            . $this->plugin->getServer()->getIp()
        ];

        return implode(
            "\n",
            $lines
        );
    }

    private function prefix(): string
    {
        return $this->plugin->getConfigManager()->getPrefix();
    }
}