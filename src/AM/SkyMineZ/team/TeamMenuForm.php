<?php

declare(strict_types=1);

namespace AM\SkyMineZ\team;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\ui\Ui;
use pocketmine\player\Player;

/**
 * The team window: your team at a glance, invitations waiting for you,
 * incoming duel challenges, and the actions each state allows.
 *
 * Everything here reuses {@link Ui}: button lists, text inputs and confirm
 * dialogs. Nothing builds a form payload by hand.
 */
final class TeamMenuForm
{
    public function __construct(
        private Main $plugin
    ) {
    }

    public function send(
        Player $player
    ): bool {
        $manager = $this->plugin->getTeamManager();
        $own = $manager->getPlayerTeam($player->getName());

        $handlers = [];

        if ($own === null) {
            $handlers['§aCreate a team'] = function(Player $who): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'Create team',
                    'Team name',
                    function(Player $w, string $name): void {
                        try {
                            $this->plugin->getTeamManager()->create($name, $w->getName());
                        } catch (\Throwable $exception) {
                            Ui::error($this->plugin, $w, $exception->getMessage());

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_CREATED, ['name' => $name]));

                        $this->send($w);
                    },
                    'MyTeam'
                );
            };
        } else {
            $handlers['§eMy team: ' . $own->getName()] = function(Player $who): void {
                $this->sendInfo($who);
            };

            $handlers['§cLeave ' . $own->getName()] = function(Player $who): void {
                Ui::confirm(
                    $this->plugin,
                    $who,
                    'Leave team',
                    'Really leave your team? If you own it and members remain, the oldest member becomes owner.',
                    function(Player $w): void {
                        $team = $this->plugin->getTeamManager()->leave($w->getName());

                        if ($team === null) {
                            Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_NOT_IN_TEAM));

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_LEFT, ['name' => $team->getName()]));
                    }
                );
            };
        }

        $invite = $manager->getInvite($player->getName());

        if ($invite !== null) {
            $handlers['§aInvitation: ' . $invite['team']] = function(Player $who): void {
                $team = $this->plugin->getTeamManager()->acceptInvite($who->getName());

                if ($team === null) {
                    Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::TEAM_INVITE_INVALID));

                    return;
                }

                Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::TEAM_JOINED, ['name' => $team->getName()]));
                $this->send($who);
            };
        }

        foreach ($manager->incomingChallenges($player->getName()) as $id => $challenge) {
            $handlers['§6Duel #' . $id . ' from ' . $challenge['from']] = function(Player $who) use ($id): void {
                $this->sendChallenge($who, $id);
            };
        }

        $handlers['§7All teams'] = function(Player $who): void {
            $this->sendList($who);
        };

        return Ui::menu(
            $this->plugin,
            $player,
            'Teams',
            $this->describe($player),
            $handlers
        );
    }

    private function describe(
        Player $player
    ): string {
        $manager = $this->plugin->getTeamManager();
        $own = $manager->getPlayerTeam($player->getName());

        if ($own === null) {
            return "§7You are not in a team.\n§7Create one or wait for an invitation.";
        }

        return "§7Team: §f" . $own->getName()
            . "\n§7Level: §f" . $own->getLevel()
            . " §8| §7Wins: §a" . $own->getWins()
            . " §8| §7Losses: §c" . $own->getLosses()
            . "\n§7Owner: §f" . $own->getOwner();
    }

    private function sendInfo(
        Player $player
    ): void {
        $own = $this->plugin->getTeamManager()->getPlayerTeam($player->getName());

        if ($own === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::TEAM_NOT_IN_TEAM));

            return;
        }

        $handlers = [];

        if ($own->isOwner($player->getName())) {
            $handlers['§aInvite a player'] = function(Player $who): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'Invite player',
                    'Player name',
                    function(Player $w, string $target): void {
                        if (!$this->plugin->getTeamManager()->invite($w->getName(), $target)) {
                            Ui::error(
                                $this->plugin,
                                $w,
                                Messages::get($this->plugin, Messages::TEAM_INVITE_FAIL)
                            );

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_INVITED, ['player' => $target]));

                        $online = $this->plugin->getServer()->getPlayerExact($target);

                        $online?->sendMessage(
                            $this->plugin->getConfigManager()->getPrefix()
                            . Messages::get(
                                $this->plugin,
                                Messages::TEAM_INVITE_RECEIVED,
                                ['player' => $w->getName()]
                            )
                        );
                    },
                    'Steve'
                );
            };

            $handlers['§6Challenge a team'] = function(Player $who): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'Challenge team',
                    'Team name',
                    function(Player $w, string $target): void {
                        $manager = $this->plugin->getTeamManager();
                        $id = $manager->challenge($w->getName(), $target);

                        if ($id === null) {
                            Ui::error(
                                $this->plugin,
                                $w,
                                Messages::get($this->plugin, Messages::TEAM_DUEL_CHALLENGE_FAIL)
                            );

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_DUEL_SENT, ['id' => $id, 'team' => $target]));

                        $other = $manager->get($target);

                        if ($other !== null) {
                            $owner = $this->plugin->getServer()->getPlayerExact($other->getOwner());

                            $owner?->sendMessage(
                                $this->plugin->getConfigManager()->getPrefix()
                                . Messages::get(
                                    $this->plugin,
                                    Messages::TEAM_DUEL_RECEIVED,
                                    ['player' => $w->getName(), 'team' => $other->getName(), 'id' => $id]
                                )
                            );
                        }
                    },
                    'Rivals'
                );
            };

            $handlers['§cKick a member'] = function(Player $who): void {
                Ui::input(
                    $this->plugin,
                    $who,
                    'Kick member',
                    'Player name',
                    function(Player $w, string $target): void {
                        if (!$this->plugin->getTeamManager()->kick($w->getName(), $target)) {
                            Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_KICK_FAIL));

                            return;
                        }

                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_KICKED, ['player' => $target]));
                    },
                    'Steve'
                );
            };

            $handlers['§cDisband team'] = function(Player $who): void {
                Ui::confirm(
                    $this->plugin,
                    $who,
                    'Disband team?',
                    'This deletes the team for all members. This cannot be undone.',
                    function(Player $w): void {
                        $team = $this->plugin->getTeamManager()->getPlayerTeam($w->getName());

                        if ($team === null || !$team->isOwner($w->getName())) {
                            Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_DISBAND_OWNER));

                            return;
                        }

                        $this->plugin->getTeamManager()->disband($team->getName());
                        Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_DISBANDED_MENU));
                    }
                );
            };
        }

        $handlers['§7Leave team'] = function(Player $who): void {
            Ui::confirm(
                $this->plugin,
                $who,
                'Leave team?',
                'You will leave your current team.',
                function(Player $w): void {
                    $team = $this->plugin->getTeamManager()->leave($w->getName());

                    if ($team === null) {
                        Ui::error($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_NOT_IN_TEAM));

                        return;
                    }

                    Ui::success($this->plugin, $w, Messages::get($this->plugin, Messages::TEAM_LEFT, ['name' => $team->getName()]));
                }
            );
        };

        $handlers['§7Back'] = function(Player $who): void {
            $this->send($who);
        };

        Ui::menu(
            $this->plugin,
            $player,
            $own->getName(),
            "§7Owner: §f" . $own->getOwner()
            . "\n§7Level §f" . $own->getLevel()
            . " §8(§f" . $own->getXp() . " XP§8)"
            . "\n§7Record: §a" . $own->getWins() . "W §c" . $own->getLosses() . "L"
            . "\n§7Members: §f" . implode(', ', $own->getMembers()),
            $handlers
        );
    }

    private function sendChallenge(
        Player $player,
        int $id
    ): void {
        $challenge = $this->plugin->getTeamManager()->getChallenge($id);

        if ($challenge === null) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::TEAM_CHALLENGE_EXPIRED));

            return;
        }

        Ui::menu(
            $this->plugin,
            $player,
            'Duel #' . $id,
            "§7From: §f" . $challenge['from']
            . "\n§7Challenged by: §f" . $challenge['by']
            . "\n\n§7Accepting starts the battle immediately.",
            [
                '§aAccept and fight' => function(Player $who) use ($id): void {
                    if (!$this->plugin->getTeamManager()->acceptChallenge($who->getName(), $id)) {
                        Ui::error($this->plugin, $who, Messages::get($this->plugin, Messages::TEAM_DUEL_START_FAIL));

                        return;
                    }

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::TEAM_DUEL_ACCEPTED));
                },
                '§cDecline' => function(Player $who) use ($id): void {
                    $this->plugin->getTeamManager()->denyChallenge($who->getName(), $id);

                    Ui::success($this->plugin, $who, Messages::get($this->plugin, Messages::TEAM_DUEL_DECLINED));
                    $this->send($who);
                }
            ]
        );
    }

    private function sendList(
        Player $player
    ): void {
        $handlers = [];

        foreach ($this->plugin->getTeamManager()->getAll() as $team) {
            $handlers['§f' . $team->getName() . ' §8(Lv' . $team->getLevel() . ')'] =
                function(Player $who) use ($team): void {
                    Ui::menu(
                        $this->plugin,
                        $who,
                        $team->getName(),
                        "§7Owner: §f" . $team->getOwner()
                        . "\n§7Level §f" . $team->getLevel()
                        . "\n§7Record: §a" . $team->getWins() . "W §c" . $team->getLosses() . "L"
                        . "\n§7Members: §f" . implode(', ', $team->getMembers()),
                        ['§7Back' => function(Player $w): void {
                            $this->send($w);
                        }]
                    );
                };
        }

        if ($handlers === []) {
            Ui::error($this->plugin, $player, Messages::get($this->plugin, Messages::TEAM_NONE));

            return;
        }

        $handlers['§7Back'] = function(Player $who): void {
            $this->send($who);
        };

        Ui::menu(
            $this->plugin,
            $player,
            'All teams',
            '§7Pick a team to inspect it.',
            $handlers
        );
    }
}