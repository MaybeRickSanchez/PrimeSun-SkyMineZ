<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\leaderboard\Leaderboard;
use AM\SkyMineZ\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * /lb - creates leaderboards and edits their title and position.
 */
final class LeaderboardCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'lb',
            'Manage SkyMineZ leaderboards',
            '/lb <create|remove|list|info|title|setpos|refresh> ...',
            ['leaderboard', 'leaderboards']
        );
    }

    /**
     * @param list<string> $args
     */
    public function execute(
        CommandSender $sender,
        string $label,
        array $args
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        $sub = strtolower(
            $args[0] ?? 'help'
        );

        return match ($sub) {
            'create', 'new' => $this->handleCreate($sender, $args),
            'remove', 'delete' => $this->handleRemove($sender, $args),
            'list' => $this->handleList($sender),
            'info' => $this->handleInfo($sender, $args),
            'title' => $this->handleTitle($sender, $args),
            'setpos' => $this->handleSetPosition($sender, $args),
            'refresh' => $this->handleRefresh($sender),
            default => $this->handleHelp($sender)
        };
    }

    /**
     * @param list<string> $args
     */
    private function handleCreate(
        CommandSender $sender,
        array $args
    ): bool {
        return $this->runPlayerSubCommand(
            $sender,
            $args,
            function(
                Player $player,
                array $args
            ): void {
                $name = $args[1] ?? null;
                $type = strtolower(
                    $args[2] ?? ''
                );

                if ($name === null || $type === '') {
                    $this->error(
                        $player,
                        'Usage: /lb create <name> <type>'
                    );

                    $player->sendMessage(
                        $this->prefixed(
                            '§7Types: ' . implode(
                                ', ',
                                Leaderboard::getTypes()
                            )
                        )
                    );

                    return;
                }

                if (!Leaderboard::isValidType($type)) {
                    $this->error(
                        $player,
                        "Unknown type '{$type}'. Use one of: "
                        . implode(
                            ', ',
                            Leaderboard::getTypes()
                        ) . '.'
                    );

                    return;
                }

                $position = TargetResolver::lookedAtPosition(
                    $player
                ) ?? $player->getPosition();

                $title = $this->joinArguments(
                    $args,
                    3
                );

                try {
                    $this->plugin->getLeaderboardManager()->add(
                        $name,
                        $type,
                        $position,
                        $title === '' ? null : $title
                    );
                } catch (\Throwable $exception) {
                    $this->fail(
                        $player,
                        $exception
                    );

                    return;
                }

                $this->success(
                    $player,
                    "Created leaderboard '{$name}' ({$type})."
                );
            }
        );
    }

    /**
     * @param list<string> $args
     */
    private function handleRemove(
        CommandSender $sender,
        array $args
    ): bool {
        $name = $args[1] ?? null;

        if ($name === null) {
            $this->error(
                $sender,
                'Usage: /lb remove <name>'
            );

            return true;
        }

        $removed = $this->plugin->getLeaderboardManager()->remove(
            $name
        );

        $removed
            ? $this->success(
                $sender,
                "Removed leaderboard '{$name}'."
            )
            : $this->error(
                $sender,
                "No leaderboard named '{$name}'."
            );

        return true;
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $manager = $this->plugin->getLeaderboardManager();

        if ($manager->count() === 0) {
            $this->info(
                $sender,
                'No leaderboards are configured.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eLeaderboards (' . $manager->count() . '):'
            )
        );

        foreach (
            $manager->getAll() as $name => $leaderboard
        ) {
            $position = $leaderboard->getPosition();

            $sender->sendMessage(
                $this->prefixed(
                    '§f' . $name . ' §8| §7' . $leaderboard->getType()
                    . ' §8| §7' . $leaderboard->getWorld()->getFolderName()
                    . ' §8(' . $position->getFloorX() . ', '
                    . $position->getFloorY() . ', '
                    . $position->getFloorZ() . ')'
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleInfo(
        CommandSender $sender,
        array $args
    ): bool {
        $leaderboard = $this->resolveLeaderboard($sender, $args[1] ?? null);

        if ($leaderboard === null) {
            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eLeaderboard ' . $leaderboard->getName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Type: §f' . $leaderboard->getType()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Title: §f' . $leaderboard->getTitle()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7World: §f' . $leaderboard->getWorld()->getFolderName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Position: §f' . $this->format(
                    $leaderboard->getPosition()
                )
            )
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleTitle(
        CommandSender $sender,
        array $args
    ): bool {
        $name = $args[1] ?? null;
        $title = $this->joinArguments(
            $args,
            2
        );

        if ($name === null || $title === '') {
            $this->error(
                $sender,
                'Usage: /lb title <name> <title>'
            );

            return true;
        }

        $leaderboard = $this->resolveLeaderboard($sender, $name);

        if ($leaderboard === null) {
            return true;
        }

        $leaderboard->setTitle($title);

        $this->plugin->getLeaderboardManager()->refreshAll();
        $this->plugin->getLeaderboardManager()->save($name);

        $this->success(
            $sender,
            'Title updated.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleSetPosition(
        CommandSender $sender,
        array $args
    ): bool {
        return $this->runPlayerSubCommand(
            $sender,
            $args,
            function(
                Player $player,
                array $args
            ): void {
                $leaderboard = $this->resolveLeaderboard($player, $args[1] ?? null);

                if ($leaderboard === null) {
                    return;
                }

                $position = TargetResolver::lookedAtPosition(
                    $player
                ) ?? $player->getPosition();

                $leaderboard->setPosition($position);

                $this->plugin->getLeaderboardManager()->refreshAll();
                $this->plugin->getLeaderboardManager()->save(
                    $leaderboard->getName()
                );

                $this->success(
                    $player,
                    'Leaderboard moved to ' . $this->format($position) . '.'
                );
            }
        );
    }

    private function handleRefresh(
        CommandSender $sender
    ): bool {
        $this->plugin->getLeaderboardManager()->refreshAll();

        $this->success(
            $sender,
            'Refreshed all leaderboards.'
        );

        return true;
    }

    private function format(
        Position $position
    ): string {
        return $position->getWorld()->getFolderName()
            . ' ('
            . $position->getFloorX() . ', '
            . $position->getFloorY() . ', '
            . $position->getFloorZ() . ')';
    }

    /**
     * Looks a leaderboard up by name, reporting the miss to the sender.
     */
    private function resolveLeaderboard(
        CommandSender $sender,
        ?string $name
    ): ?Leaderboard {
        $leaderboard = $name !== null && $name !== ''
            ? $this->plugin->getLeaderboardManager()->get($name)
            : null;

        if ($leaderboard === null) {
            $this->error(
                $sender,
                "No leaderboard named '" . ($name ?? '') . "'."
            );

            return null;
        }

        return $leaderboard;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/lb create <name> <type> [title] §7- create a board where you look',
            '§7Types: ' . implode(
                ', ',
                Leaderboard::getTypes()
            ),
            '§e/lb remove <name> §7- delete a board',
            '§e/lb title <name> <title> §7- change the title',
            '§e/lb setpos <name> §7- move the board',
            '§e/lb refresh §7- recompute every board now',
            '§e/lb info <name> §7- details',
            '§e/lb list §7- list all boards'
        ];

        $sender->sendMessage(
            $this->prefixed('§eSkyMineZ leaderboards')
        );

        foreach (
            $lines as $line
        ) {
            $sender->sendMessage(
                $this->prefixed($line)
            );
        }

        return true;
    }
}