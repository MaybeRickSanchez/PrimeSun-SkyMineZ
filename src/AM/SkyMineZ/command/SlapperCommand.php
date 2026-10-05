<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\slapper\Slapper;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /slapper - creates NPC slappers and the blocks attached to them.
 *
 * A slapper runs a list of commands and prints a list of messages when
 * right-clicked. `{player}` is replaced with the clicker's name in both.
 */
final class SlapperCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'slapper',
            'Manage SkyMineZ slappers',
            '/slapper <create|remove|list|move|msg|cmd|block> ...',
            ['slappers', 'npc']
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
            'move' => $this->handleMove($sender, $args),
            'msg', 'message' => $this->handleMessage($sender, $args),
            'cmd', 'command' => $this->handleCommand($sender, $args),
            'block' => $this->handleBlock($sender, $args),
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
                        'Usage: /slapper create <name>  '
                        . '(letters, digits, underscore and dash only)'
                    );

                    return;
                }

                try {
                    $this->plugin->getSlapperManager()->addSlapper(
                        $name,
                        $player->getPosition(),
                        $player
                    );
                } catch (\Throwable $exception) {
                    $this->fail(
                        $player,
                        $exception
                    );

                    return;
                }

                $this->plugin->getSlapperManager()->save(
                    $name
                );

                $this->success(
                    $player,
                    "Created slapper '{$name}' with your skin."
                );

                $player->sendMessage(
                    $this->prefixed(
                        '§7Add behaviour: /slapper msg add ' . $name
                        . ' <text>  |  /slapper cmd add ' . $name . ' <command>'
                    )
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
                'Usage: /slapper remove <name>'
            );

            return true;
        }

        $removed = $this->plugin->getSlapperManager()->removeSlapper(
            $name
        );

        if (!$removed) {
            $this->error(
                $sender,
                "No slapper named '{$name}'."
            );

            return true;
        }

        $this->plugin->getSlapperManager()->saveAll();

        $this->success(
            $sender,
            "Removed slapper '{$name}' and its blocks."
        );

        return true;
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $manager = $this->plugin->getSlapperManager();
        $slappers = $manager->getSlappers();

        if ($slappers === []) {
            $this->info(
                $sender,
                'No slappers are configured.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eSlappers (' . count($slappers) . '):'
            )
        );

        foreach (
            $slappers as $name => $slapper
        ) {
            $location = $slapper->getLocation();

            $sender->sendMessage(
                $this->prefixed(
                    '§f' . $name . ' §8| §7'
                    . $location->getWorld()->getFolderName()
                    . ' §8| §7'
                    . count($slapper->getCommands()) . ' commands §8| §7'
                    . count($slapper->getMessages()) . ' messages'
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleMove(
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

                if ($name === null) {
                    $this->error(
                        $player,
                        'Usage: /slapper move <name>'
                    );

                    return;
                }

                $slapper = $this->resolveSlapper($player, $name);

                if ($slapper === null) {
                    return;
                }

                if (
                    !$this->plugin->getSlapperManager()->move(
                        $name,
                        $player->getPosition()
                    )
                ) {
                    $this->error(
                        $player,
                        "No slapper named '{$name}'."
                    );

                    return;
                }

                $this->success(
                    $player,
                    "Moved slapper '{$name}'."
                );
            }
        );
    }

    /**
     * @param list<string> $args
     */
    private function handleMessage(
        CommandSender $sender,
        array $args
    ): bool {
        $action = strtolower(
            $args[1] ?? 'list'
        );

        $slapper = $this->plugin->getSlapperManager()->getSlapper(
            $args[2] ?? ''
        );

        if ($slapper === null) {
            $this->error(
                $sender,
                'Usage: /slapper msg <add|remove|clear|list> <name> [text]'
            );

            return true;
        }

        $text = $this->joinArguments(
            $args,
            3
        );

        switch ($action) {
            case 'add':
                if ($text === '') {
                    $this->error(
                        $sender,
                        'Usage: /slapper msg add <name> <text>'
                    );

                    return true;
                }

                $slapper->addMessage($text);
                break;

            case 'remove':
                $index = is_numeric($text)
                    ? (int) $text
                    : -1;

                if (!$slapper->removeMessage($index)) {
                    $this->error(
                        $sender,
                        'Message index out of range.'
                    );

                    return true;
                }
                break;

            case 'clear':
                $slapper->clearMessages();
                break;

            case 'list':
                $messages = $slapper->getMessages();

                if ($messages === []) {
                    $this->info(
                        $sender,
                        'That slapper has no messages.'
                    );

                    return true;
                }

                foreach (
                    $messages as $index => $message
                ) {
                    $sender->sendMessage(
                        $this->prefixed(
                            '§f#' . $index . ' §8| §7' . $message
                        )
                    );
                }

                return true;

            default:
                $this->error(
                    $sender,
                    'Use add, remove, clear or list.'
                );

                return true;
        }

        $this->plugin->getSlapperManager()->save(
            $slapper->getName()
        );

        $this->success(
            $sender,
            'Messages updated.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleCommand(
        CommandSender $sender,
        array $args
    ): bool {
        $action = strtolower(
            $args[1] ?? 'list'
        );

        $slapper = $this->plugin->getSlapperManager()->getSlapper(
            $args[2] ?? ''
        );

        if ($slapper === null) {
            $this->error(
                $sender,
                'Usage: /slapper cmd <add|remove|clear|list> <name> [command]'
            );

            return true;
        }

        $text = $this->joinArguments(
            $args,
            3
        );

        switch ($action) {
            case 'add':
                if ($text === '') {
                    $this->error(
                        $sender,
                        'Usage: /slapper cmd add <name> <command>'
                    );

                    return true;
                }

                $slapper->addCommand($text);
                break;

            case 'remove':
                $index = is_numeric($text)
                    ? (int) $text
                    : -1;

                if (!$slapper->removeCommand($index)) {
                    $this->error(
                        $sender,
                        'Command index out of range.'
                    );

                    return true;
                }
                break;

            case 'clear':
                $slapper->clearCommands();
                break;

            case 'list':
                $commands = $slapper->getCommands();

                if ($commands === []) {
                    $this->info(
                        $sender,
                        'That slapper has no commands.'
                    );

                    return true;
                }

                foreach (
                    $commands as $index => $command
                ) {
                    $sender->sendMessage(
                        $this->prefixed(
                            '§f#' . $index . ' §8| §7' . $command
                        )
                    );
                }

                return true;

            default:
                $this->error(
                    $sender,
                    'Use add, remove, clear or list.'
                );

                return true;
        }

        $this->plugin->getSlapperManager()->save(
            $slapper->getName()
        );

        $this->success(
            $sender,
            'Commands updated.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleBlock(
        CommandSender $sender,
        array $args
    ): bool {
        $manager = $this->plugin->getSlapperManager();

        $action = strtolower(
            $args[1] ?? 'list'
        );

        switch ($action) {
            case 'add':
                return $this->runPlayerSubCommand(
                    $sender,
                    $args,
                    function(
                        Player $player,
                        array $args
                    ) use ($manager): void {
                        $slapperName = $args[2] ?? null;
                        $blockName = $args[3] ?? null;
                        $label = $args[4] ?? (
                            $slapperName ?? 'slapper'
                        );

                        if (
                            $slapperName === null
                            || $blockName === null
                        ) {
                            $this->error(
                                $player,
                                'Usage: /slapper block add <slapper> <block> [label]'
                            );

                            return;
                        }

                        $slapper = $this->resolveSlapper($player, $slapperName);

                        if ($slapper === null) {
                            return;
                        }

                        $block = BlockParser::parse($blockName);

                        if ($block === null) {
                            $this->error(
                                $player,
                                "Unknown block '{$blockName}'."
                            );

                            return;
                        }

                        $position = TargetResolver::blockOrBelow(
                            $player
                        )->getPosition();

                        $blockNameKey = self::isValidName($label)
                            ? $label
                            : $slapperName;

                        try {
                            $manager->addBlock(
                                $blockNameKey,
                                $position,
                                $block,
                                $slapperName
                            );
                        } catch (\Throwable $exception) {
                            $this->fail(
                                $player,
                                $exception
                            );

                            return;
                        }

                        $manager->save(
                            $slapperName
                        );

                        $this->success(
                            $player,
                            "Added slapper block '{$blockNameKey}'."
                        );
                    }
                );

            case 'remove':
                $name = $args[2] ?? null;

                if ($name === null) {
                    $this->error(
                        $sender,
                        'Usage: /slapper block remove <name>'
                    );

                    return true;
                }

                $removed = $manager->removeBlock($name);

                if (!$removed) {
                    $this->error(
                        $sender,
                        "No slapper block named '{$name}'."
                    );

                    return true;
                }

                $manager->saveAll();

                $this->success(
                    $sender,
                    "Removed slapper block '{$name}'."
                );

                return true;

            case 'list':
                $blocks = $manager->getSlapperBlocks();

                if ($blocks === []) {
                    $this->info(
                        $sender,
                        'No slapper blocks are configured.'
                    );

                    return true;
                }

                $sender->sendMessage(
                    $this->prefixed(
                        '§eSlapper blocks (' . count($blocks) . '):'
                    )
                );

                foreach (
                    $blocks as $name => $block
                ) {
                    $position = $block->getPosition();

                    $sender->sendMessage(
                        $this->prefixed(
                            '§f' . $name . ' §8| §7'
                            . $block->getBlock()->getName()
                            . ' §8| §7'
                            . $block->getSlapperName()
                            . ' §8| §7'
                            . $position->getWorld()->getFolderName()
                            . ' (' . $position->getFloorX() . ', '
                            . $position->getFloorY() . ', '
                            . $position->getFloorZ() . ')'
                        )
                    );
                }

                return true;

            default:
                $this->error(
                    $sender,
                    'Use add, remove or list.'
                );

                return true;
        }
    }

    /**
     * Looks a slapper up by name, reporting the miss to the sender.
     */
    private function resolveSlapper(
        CommandSender $sender,
        ?string $name
    ): ?Slapper {
        $slapper = $name !== null && $name !== ''
            ? $this->plugin->getSlapperManager()->getSlapper($name)
            : null;

        if ($slapper === null) {
            $this->error(
                $sender,
                "No slapper named '" . ($name ?? '') . "'."
            );

            return null;
        }

        return $slapper;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/slapper create <name> §7- spawn an NPC with your skin at your feet',
            '§e/slapper remove <name> §7- delete it and its blocks',
            '§e/slapper move <name> §7- move it to where you stand',
            '§e/slapper msg <add|remove|clear|list> <name> [text]',
            '§e/slapper cmd <add|remove|clear|list> <name> [command]',
            '§e/slapper block <add|remove|list> ...',
            '§7Placeholders: {player} and {name} are the clicker.',
            '§e/slapper list §7- list all slappers'
        ];

        $sender->sendMessage(
            $this->prefixed('§eSkyMineZ slappers')
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