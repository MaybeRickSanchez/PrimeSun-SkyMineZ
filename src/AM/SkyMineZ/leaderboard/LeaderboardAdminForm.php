<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\command\TargetResolver;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use AM\SkyMineZ\useless\Positions;
use pocketmine\player\Player;

/**
 * Leaderboard management window: create boards where you stand, retitle,
 * relocate, refresh and delete them.
 */
final class LeaderboardAdminForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $boards = $this->plugin->getLeaderboardManager()->getAll();

        $handlers = [
            '§aCreate board here' => function(Player $who): void {
                $this->sendCreate($who);
            }
        ];

        foreach ($boards as $name => $board) {
            $handlers['§d' . $name . ' §8(' . $board->getType() . ')'] =
                function(Player $who) use ($name): void {
                    $this->sendBoard($who, $name);
                };
        }

        return Ui::menu(
            $this->plugin,
            $player,
            'Leaderboards',
            $boards === []
                ? '§7No boards yet. Create the first one where you stand.'
                : '§7Pick a board to manage it.',
            $handlers
        );
    }

    private function sendCreate(
        Player $player
    ): void {
        Ui::input(
            $this->plugin,
            $player,
            'New board',
            'Name',
            function(Player $who, string $name): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'Board type',
                    'One of: ' . implode(', ', Leaderboard::getTypes()),
                    function(Player $w, string $type) use ($name): void {
                        $type = strtolower(trim($type));

                        if (!Leaderboard::isValidType($type)) {
                            Ui::error(
                                $this->plugin,
                                $w,
                                Messages::get(
                                    $this->plugin,
                                    Messages::BOARD_BAD_TYPE,
                                    ['types' => implode(', ', Leaderboard::getTypes())]
                                )
                            );

                            return;
                        }

                        try {
                            $this->plugin->getLeaderboardManager()->add(
                                $name,
                                $type,
                                TargetResolver::playerMiddle($w)
                            );
                        } catch (\Throwable $exception) {
                            Ui::error($this->plugin, $w, $exception->getMessage());

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::BOARD_CREATED, ['name' => $name]));
                        $this->sendBoard($w, $name);
                    },
                    'money'
                );
            },
            'top'
        );
    }

    private function sendBoard(
        Player $player,
        string $name
    ): void {
        $board = $this->plugin->getLeaderboardManager()->get($name);

        if ($board === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::BOARD_GONE));

            return;
        }

        Ui::menu(
            $this->plugin,
            $player,
            $name,
            '§7Type: §f' . $board->getType()
            . "\n§7Title: §f" . $board->getTitle()
            . "\n§7At: §f" . Positions::describe($board->getPosition()),
            [
                '§eRetitle' => function(Player $who) use ($name): void {
                    Ui::input(
                        $this->plugin,
                        $who,
                        'Board title',
                        'New title',
                        function(Player $w, string $title) use ($name): void {
                            $board = $this->plugin->getLeaderboardManager()->get($name);

                            if ($board === null) {
                                return;
                            }

                            $board->setTitle($title);
                            $this->plugin->getLeaderboardManager()->refreshAll();
                            $this->plugin->getLeaderboardManager()->save($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::BOARD_TITLE_SET));
                            $this->sendBoard($w, $name);
                        },
                        $this->plugin->getLeaderboardManager()->get($name)?->getTitle() ?? ''
                    );
                },
                '§eMove here' => function(Player $who) use ($name): void {
                    $board = $this->plugin->getLeaderboardManager()->get($name);

                    if ($board === null) {
                        return;
                    }

                    $board->setPosition(
                        TargetResolver::playerMiddle($who)
                    );

                    $this->plugin->getLeaderboardManager()->refreshAll();
                    $this->plugin->getLeaderboardManager()->save($name);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::BOARD_MOVED));
                },
                '§aRefresh now' => function(Player $who): void {
                    $this->plugin->getLeaderboardManager()->refreshAll();

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::BOARD_REFRESHED));
                },
                '§cDelete board' => function(Player $who) use ($name): void {
                    Ui::confirm(
                        $this->plugin,
                        $who,
                        'Delete board?',
                        "This removes '{$name}' and its hologram.",
                        function(Player $w) use ($name): void {
                            $this->plugin->getLeaderboardManager()->remove($name);

                            Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::COMMON_DELETED, ['name' => $name]));
                        }
                    );
                },
                '§7Back' => function(Player $who): void {
                    $this->send($who);
                }
            ]
        );
    }
}