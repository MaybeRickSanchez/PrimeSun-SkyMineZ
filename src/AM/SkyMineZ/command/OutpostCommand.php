<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\outpost\Outpost;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /outpost - creates outposts and inspects or overrides their ownership.
 */
final class OutpostCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'outpost',
            'Manage SkyMineZ outposts',
            '/outpost <create|remove|list|info|owner|setpos|reset> ...',
            ['outposts']
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
            'owner' => $this->handleOwner($sender, $args),
            'reset' => $this->handleReset($sender, $args),
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

                if ($name === null || !self::isValidName($name)) {
                    $this->error(
                        $player,
                        'Usage: /outpost create <name>  '
                        . '(letters, digits, underscore and dash only)'
                    );

                    return;
                }

                $region = $this->plugin->getSelectionManager()
                    ->getRegion($player);

                if ($region === null) {
                    $this->error(
                        $player,
                        'Select the region first: /skymine pos1 and /skymine pos2.'
                    );

                    return;
                }

                [$pos1, $pos2] = $region;

                try {
                    $outpost = $this->plugin->getOutpostManager()->create(
                        $name,
                        $pos1,
                        $pos2
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
                    "Created outpost '{$name}' capturing in "
                    . $outpost->getInfo()->getPosition()?->getWorld()->getFolderName()
                    . '.'
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
                'Usage: /outpost remove <name>'
            );

            return true;
        }

        $removed = $this->plugin->getOutpostManager()->remove(
            $name
        );

        $removed
            ? $this->success(
                $sender,
                "Removed outpost '{$name}'."
            )
            : $this->error(
                $sender,
                "No outpost named '{$name}'."
            );

        return true;
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $manager = $this->plugin->getOutpostManager();

        if ($manager->count() === 0) {
            $this->info(
                $sender,
                'No outposts are configured.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eOutposts (' . $manager->count() . '):'
            )
        );

        foreach (
            $manager->getAll() as $name => $outpost
        ) {
            $sender->sendMessage(
                $this->prefixed(
                    '§f' . $name . ' §8| §7'
                    . $outpost->getWorld()->getFolderName()
                    . ' §8| §7owner §f'
                    . ($outpost->getOwner() ?? 'none')
                    . ' §8| §7'
                    . ($outpost->isCapturable()
                        ? '§acapturable'
                        : '§clocked')
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
        $outpost = $this->plugin->getOutpostManager()->get(
            $args[1] ?? ''
        );

        if ($outpost === null) {
            $this->error(
                $sender,
                'No outpost with that name.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eOutpost ' . $outpost->getName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7World: §f' . $outpost->getWorld()->getFolderName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7State: §f' . $outpost->getState()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Owner: §f' . ($outpost->getOwner() ?? 'none')
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Progress: §f' . $outpost->getProgress()
                . '/' . $outpost->getCaptureRequired()
            )
        );

        $availableAt = $outpost->getAvailableAt();

        if ($availableAt > time()) {
            $sender->sendMessage(
                $this->prefixed(
                    '§7Unlocks in: §e'
                    . NumberFormatter::duration(
                        $availableAt - time()
                    )
                )
            );
        }

        $owned = $this->plugin->getOutpostManager()->getOwnedBy(
            $outpost->getOwner() ?? ''
        );

        if ($owned !== []) {
            $sender->sendMessage(
                $this->prefixed(
                    '§7Owned outposts: §f' . count($owned)
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleOwner(
        CommandSender $sender,
        array $args
    ): bool {
        $outpost = $this->plugin->getOutpostManager()->get(
            $args[1] ?? ''
        );

        if ($outpost === null) {
            $this->error(
                $sender,
                'No outpost with that name.'
            );

            return true;
        }

        $playerName = $args[2] ?? null;

        if ($playerName === null) {
            $this->error(
                $sender,
                'Usage: /outpost owner <name> <player|clear>'
            );

            return true;
        }

        if (
            strcasecmp(
                $playerName,
                'clear'
            ) === 0
        ) {
            $outpost->setOwner(null);
        } else {
            $outpost->setOwner($playerName);
        }

        $this->plugin->getOutpostManager()->save(
            $outpost->getName()
        );

        $this->success(
            $sender,
            'Owner set to ' . ($outpost->getOwner() ?? 'none') . '.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleReset(
        CommandSender $sender,
        array $args
    ): bool {
        $outpost = $this->plugin->getOutpostManager()->get(
            $args[1] ?? ''
        );

        if ($outpost === null) {
            $this->error(
                $sender,
                'No outpost with that name.'
            );

            return true;
        }

        $outpost->restore(
            $outpost->getOwner(),
            Outpost::STATE_CAPTABLE,
            0,
            0,
            0
        );

        $this->plugin->getOutpostManager()->save(
            $outpost->getName()
        );

        $this->success(
            $sender,
            'Outpost unlocked and progress cleared.'
        );

        return true;
    }

    private static function isValidName(
        string $name
    ): bool {
        return preg_match(
            '/^[A-Za-z0-9_-]{1,32}$/',
            $name
        ) === 1;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/outpost create <name> §7- create an outpost from the pos1/pos2 selection',
            '§e/outpost remove <name> §7- delete an outpost',
            '§e/outpost owner <name> <player|clear> §7- force the owner',
            '§e/outpost reset <name> §7- unlock it and clear progress',
            '§e/outpost info <name> §7- details',
            '§e/outpost list §7- list all outposts'
        ];

        $sender->sendMessage(
            $this->prefixed('§eSkyMineZ outposts')
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