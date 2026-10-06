<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\label\LabelManager;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use AM\SkyMineZ\useless\Positions;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /label - floating multi-line labels for help boards, tutorials, area signs
 * and server information.
 */
final class LabelCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'label',
            'Manage floating text labels',
            '/label <create|set|addline|delline|move|remove|list|info> ...',
            ['labels']
        );
    }

    public function execute(
        CommandSender $sender,
        string $label,
        array $args
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        $sub = strtolower($args[0] ?? 'help');

        return match ($sub) {
            'create', 'new' => $this->handleCreate($sender, $args),
            'set' => $this->handleSet($sender, $args),
            'addline' => $this->handleAddLine($sender, $args),
            'delline' => $this->handleDelLine($sender, $args),
            'move' => $this->handleMove($sender, $args),
            'remove', 'delete' => $this->handleRemove($sender, $args),
            'list' => $this->handleList($sender),
            'info' => $this->handleInfo($sender, $args),
            default => $this->handleHelp($sender)
        };
    }

    private function manager(): LabelManager
    {
        return $this->plugin->getLabelManager();
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
            function(Player $player, array $args): void {
                $name = $args[1] ?? null;

                if ($name === null || !self::isValidName($name)) {
                    $this->error(
                        $player,
                        Messages::get($this->plugin, Messages::LABEL_CREATE_USAGE)
                    );

                    return;
                }

                $first = $this->joinArguments($args, 2);

                try {
                    $this->manager()->create(
                        $name,
                        TargetResolver::lookedAtPosition($player)
                    ?? Positions::above($player->getPosition(), 2.0),
                        $first === '' ? [''] : [$first]
                    );
                } catch (\Throwable $exception) {
                    $this->fail($player, $exception);

                    return;
                }

                $this->success(
                    $player,
                    Messages::get($this->plugin, Messages::LABEL_CREATED, ['name' => (string) $name])
                );
            }
        );
    }

    /**
     * @param list<string> $args
     */
    private function handleSet(
        CommandSender $sender,
        array $args
    ): bool {
        $name = $args[1] ?? null;
        $index = isset($args[2]) && is_numeric($args[2]) ? (int) $args[2] : -1;
        $text = $this->joinArguments($args, 3);

        $label = $name !== null ? $this->manager()->get($name) : null;

        if ($label === null || $index < 0 || $text === '') {
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_SET_USAGE));

            return true;
        }

        $label->setLine($index, $text);
        $this->manager()->save($name);

        $this->success($sender, Messages::get($this->plugin, Messages::LABEL_LINE_UPDATED, ['index' => $index, 'name' => (string) $name]));

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleAddLine(
        CommandSender $sender,
        array $args
    ): bool {
        $name = $args[1] ?? null;
        $text = $this->joinArguments($args, 2);

        $label = $name !== null ? $this->manager()->get($name) : null;

        if ($label === null || $text === '') {
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_ADD_USAGE));

            return true;
        }

        $label->setLine(count($label->getLines()), $text);
        $this->manager()->save($name);

        $this->success($sender, Messages::get($this->plugin, Messages::LABEL_ADDED, ['name' => (string) $name]));

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleDelLine(
        CommandSender $sender,
        array $args
    ): bool {
        $name = $args[1] ?? null;
        $index = isset($args[2]) && is_numeric($args[2]) ? (int) $args[2] : -1;

        $label = $name !== null ? $this->manager()->get($name) : null;

        if ($label === null || $index < 0) {
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_DEL_USAGE));

            return true;
        }

        $lines = $label->getLines();

        if (!isset($lines[$index])) {
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_RANGE));

            return true;
        }

        unset($lines[$index]);

        $label->setLines(array_values($lines));
        $this->manager()->save($name);

        $this->success($sender, Messages::get($this->plugin, Messages::LABEL_REMOVED_LINE, ['index' => $index, 'name' => (string) $name]));

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
            function(Player $player, array $args): void {
                $name = $args[1] ?? null;

                if ($name === null || !$this->manager()->has($name)) {
                    $this->error($player, Messages::get($this->plugin, Messages::LABEL_MOVE_USAGE));

                    return;
                }

                $position = TargetResolver::lookedAtPosition($player)
                    ?? Positions::above($player->getPosition(), 2.0);

                $this->manager()->move($name, $position);

                $this->success($player, Messages::get($this->plugin, Messages::LABEL_MOVED, ['name' => (string) $name]));
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
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_REMOVE_USAGE));

            return true;
        }

        $this->manager()->remove($name)
            ? $this->success($sender, Messages::get($this->plugin, Messages::COMMON_DELETED, ['name' => (string) $name]))
            : $this->error($sender, Messages::get($this->plugin, Messages::LABEL_UNKNOWN, ['name' => (string) $name]));

        return true;
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $labels = $this->manager()->getAll();

        if ($labels === []) {
            $this->info($sender, Messages::get($this->plugin, Messages::LABEL_NONE));

            return true;
        }

        $sender->sendMessage($this->prefixed(Messages::get($this->plugin, Messages::LABEL_LIST_TITLE)));

        foreach ($labels as $name => $label) {
            $sender->sendMessage(
                $this->prefixed(
                    Messages::get(
                        $this->plugin,
                        Messages::LABEL_LIST_ROW,
                        [
                            'name' => $name,
                            'count' => count($label->getLines()),
                            'where' => Positions::describe($label->getPosition())
                        ]
                    )
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
        $name = $args[1] ?? null;

        $label = $name !== null ? $this->manager()->get($name) : null;

        if ($label === null) {
            $this->error($sender, Messages::get($this->plugin, Messages::LABEL_INFO_USAGE));

            return true;
        }

        $sender->sendMessage($this->prefixed(Messages::get($this->plugin, Messages::LABEL_INFO_TITLE, ['name' => (string) $name])));

        foreach ($label->getLines() as $index => $line) {
            $sender->sendMessage(
                $this->prefixed(
                    Messages::get(
                        $this->plugin,
                        Messages::LABEL_INFO_LINE,
                        ['index' => $index, 'line' => $line]
                    )
                )
            );
        }

        return true;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        foreach ([
            Messages::get($this->plugin, Messages::LABEL_HELP_CREATE),
            Messages::get($this->plugin, Messages::LABEL_HELP_SET),
            Messages::get($this->plugin, Messages::LABEL_HELP_ADD),
            Messages::get($this->plugin, Messages::LABEL_HELP_DEL),
            Messages::get($this->plugin, Messages::LABEL_HELP_MOVE),
            Messages::get($this->plugin, Messages::LABEL_HELP_REMOVE),
            Messages::get($this->plugin, Messages::LABEL_HELP_INFO),
            Messages::get($this->plugin, Messages::LABEL_HELP_LIST)
        ] as $line) {
            $sender->sendMessage($this->prefixed($line));
        }

        return true;
    }
}