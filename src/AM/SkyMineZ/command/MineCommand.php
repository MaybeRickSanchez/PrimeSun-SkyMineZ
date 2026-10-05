<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\mine\Mine;
use AM\SkyMineZ\mine\MineBlock;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * /mine - creates mines, defines their block list and controls the reset timer.
 *
 * The cuboid comes from the player's pos1/pos2 selection (see
 * {@link SelectionManager}), so `/skymine pos1` and `/skymine pos2` are the
 * workflow for building one.
 */
final class MineCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'mine',
            'Manage SkyMineZ mines',
            '/mine <create|remove|list|info|block|pos1|pos2|setinterval|setlabel|reset> ...',
            ['mines']
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
            'block', 'blocks' => $this->handleBlock($sender, $args),
            'pos1', 'pos2' => $this->handlePosition($sender, $args),
            'setinterval' => $this->handleInterval($sender, $args),
            'setlabel' => $this->handleLabel($sender, $args),
            'reset' => $this->handleReset($sender, $args),
            'clearblocks' => $this->handleClearBlocks($sender, $args),
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
                        'Usage: /mine create <name>  '
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

                /*
                 * The label sits above the top corner of the box so it never
                 * ends up inside the ore.
                 */
                $label = new Position(
                    $pos1->getFloorX() + (
                        (int) abs(
                            $pos2->getFloorX() - $pos1->getFloorX()
                        ) / 2
                    ),
                    max(
                        $pos1->getFloorY(),
                        $pos2->getFloorY()
                    ) + 3,
                    $pos1->getFloorZ() + (
                        (int) abs(
                            $pos2->getFloorZ() - $pos1->getFloorZ()
                        ) / 2
                    ),
                    $pos1->getWorld()
                );

                try {
                    $mine = $this->plugin->getMineManager()->create(
                        $name,
                        $pos1,
                        $pos2,
                        $label
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
                    "Created mine '{$name}' (" . $mine->getMineBox()->getVolume()
                    . ' blocks).'
                );

                $player->sendMessage(
                    $this->prefixed(
                        '§7Now add blocks: /mine block add ' . $name . ' stone 60'
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
                'Usage: /mine remove <name>'
            );

            return true;
        }

        $removed = $this->plugin->getMineManager()->remove(
            $name
        );

        $removed
            ? $this->success(
                $sender,
                "Removed mine '{$name}'."
            )
            : $this->error(
                $sender,
                "No mine named '{$name}'."
            );

        return true;
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $manager = $this->plugin->getMineManager();

        if ($manager->count() === 0) {
            $this->info(
                $sender,
                'No mines are configured.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eMines (' . $manager->count() . '):'
            )
        );

        foreach (
            $manager->getAll() as $name => $mine
        ) {
            $sender->sendMessage(
                $this->prefixed(
                    '§f' . $name . ' §8| §7'
                    . $mine->getMineBox()->getVolume() . ' blocks §8| §7'
                    . count($mine->getBlocks()) . ' block types §8| §7'
                    . ($mine->getResetInterval() > 0
                        ? 'every ' . NumberFormatter::duration(
                            $mine->getResetInterval()
                        )
                        : 'manual')
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
        $mine = $this->resolveMine($sender, $args[1] ?? null);

        if ($mine === null) {
            return true;
        }

        $box = $mine->getMineBox();

        $sender->sendMessage(
            $this->prefixed(
                '§eMine ' . $mine->getName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7World: §f' . $mine->getWorld()->getFolderName()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Size: §f' . $box->getSizeX() . 'x' . $box->getSizeY()
                . 'x' . $box->getSizeZ() . ' §7(' . $box->getVolume() . ' blocks)'
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Interval: §f'
                . ($mine->getResetInterval() > 0
                    ? NumberFormatter::duration(
                        $mine->getResetInterval()
                    )
                    : 'manual only')
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7State: §f'
                . ($mine->isFilling()
                    ? 'refilling'
                    : 'idle')
                . ' §7| §7configured percentages: §f'
                . $mine->getTotalPercent() . '%'
            )
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handlePosition(
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
                $which = strtolower(
                    $args[0] ?? 'pos1'
                );

                $position = TargetResolver::lookedAtPosition(
                    $player
                ) ?? $player->getPosition();

                if ($which === 'pos1') {
                    $this->plugin->getSelectionManager()->setPos1(
                        $player,
                        $position
                    );

                    $this->success(
                        $player,
                        'pos1 set to ' . $this->format($position) . '.'
                    );

                    return;
                }

                $this->plugin->getSelectionManager()->setPos2(
                    $player,
                    $position
                );

                $this->success(
                    $player,
                    'pos2 set to ' . $this->format($position) . '.'
                );
            }
        );
    }

    /**
     * @param list<string> $args
     */
    private function handleInterval(
        CommandSender $sender,
        array $args
    ): bool {
        $mine = $this->plugin->getMineManager()->get(
            $args[1] ?? ''
        );

        $seconds = $args[2] ?? null;

        if (
            $mine === null
            || $seconds === null
            || !is_numeric($seconds)
        ) {
            $this->error(
                $sender,
                'Usage: /mine setinterval <name> <seconds>  (0 = manual only)'
            );

            return true;
        }

        $mine->setResetInterval(
            (int) $seconds
        );

        $this->plugin->getMineManager()->save(
            $mine->getName()
        );

        $this->success(
            $sender,
            'Interval set to ' . ($mine->getResetInterval() > 0
                ? NumberFormatter::duration(
                    $mine->getResetInterval()
                )
                : 'manual only') . '.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleLabel(
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
                $mine = $this->resolveMine($player, $args[1] ?? null);

                if ($mine === null) {
                    return;
                }

                $position = TargetResolver::lookedAtPosition(
                    $player
                ) ?? $player->getPosition();

                $mine->getInfo()->setPosition($position);
                $mine->getInfo()->spawn();

                $this->plugin->getMineManager()->save(
                    $mine->getName()
                );

                $this->success(
                    $player,
                    'Label moved to ' . $this->format($position) . '.'
                );
            }
        );
    }

    /**
     * @param list<string> $args
     */
    private function handleReset(
        CommandSender $sender,
        array $args
    ): bool {
        $manager = $this->plugin->getMineManager();

        $name = $args[1] ?? 'all';

        if (
            strcasecmp(
                $name,
                'all'
            ) === 0
        ) {
            $started = $manager->resetAll(
                'manual'
            );

            $started === []
                ? $this->error(
                    $sender,
                    'No mine could be reset (empty or already refilling).'
                )
                : $this->success(
                    $sender,
                    'Resetting ' . count($started) . ' mine(s).'
                );

            return true;
        }

        $mine = $this->resolveMine($sender, $name);

        if ($mine === null) {
            return true;
        }

        if (!$manager->reset(
            $name,
            'manual'
        )) {
            $this->error(
                $sender,
                'That mine has no blocks yet or is already refilling.'
            );

            return true;
        }

        $this->success(
            $sender,
            "Refilling '{$name}'."
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleClearBlocks(
        CommandSender $sender,
        array $args
    ): bool {
        $mine = $this->resolveMine($sender, $args[1] ?? null);

        if ($mine === null) {
            return true;
        }

        $mine->clearBlocks();

        $this->plugin->getMineManager()->save(
            $mine->getName()
        );

        $this->success(
            $sender,
            'Block list cleared.'
        );

        return true;
    }

    /**
     * /mine block <add|remove|list> ...
     *
     * @param list<string> $args
     */
    private function handleBlock(
        CommandSender $sender,
        array $args
    ): bool {
        $action = strtolower(
            $args[1] ?? 'list'
        );

        $mine = $this->plugin->getMineManager()->get(
            $args[2] ?? ''
        );

        if ($mine === null) {
            $this->error(
                $sender,
                'Usage: /mine block <add|remove|list> <mine> ...'
            );

            return true;
        }

        switch ($action) {
            case 'add':
                return $this->blockAdd(
                    $sender,
                    $mine,
                    $args
                );

            case 'remove':
                return $this->blockRemove(
                    $sender,
                    $mine,
                    $args
                );

            case 'list':
                return $this->blockList(
                    $sender,
                    $mine
                );

            default:
                $this->error(
                    $sender,
                    'Unknown action. Use add, remove or list.'
                );

                return true;
        }
    }

    /**
     * @param list<string> $args
     */
    private function blockAdd(
        CommandSender $sender,
        Mine $mine,
        array $args
    ): bool {
        $blockName = $args[3] ?? null;
        $percent = isset($args[4]) && is_numeric($args[4])
            ? (int) $args[4]
            : -1;

        if (
            $blockName === null
            || $percent < 1
            || $percent > 100
        ) {
            $this->error(
                $sender,
                'Usage: /mine block add <mine> <block> <percent 1-100>'
            );

            return true;
        }

        $block = BlockParser::parse($blockName);

        if ($block === null) {
            $this->error(
                $sender,
                "Unknown block '{$blockName}'. Try one of: "
                . implode(
                    ', ',
                    BlockParser::suggestions()
                ) . ', ...'
            );

            return true;
        }

        try {
            $mine->addBlock(
                new MineBlock(
                    $percent,
                    $block
                )
            );
        } catch (\InvalidArgumentException $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->plugin->getMineManager()->save(
            $mine->getName()
        );

        $this->success(
            $sender,
            "Added {$percent}% {$block->getName()} to '{$mine->getName()}'."
        );

        if ($mine->getTotalPercent() !== 100) {
            $this->info(
                $sender,
                'Percentages currently add up to '
                . $mine->getTotalPercent() . '%; they are scaled on reset.'
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function blockRemove(
        CommandSender $sender,
        Mine $mine,
        array $args
    ): bool {
        $index = isset($args[3]) && is_numeric($args[3])
            ? (int) $args[3]
            : -1;

        if (
            $index < 0
            || !isset($mine->getBlocks()[$index])
        ) {
            $this->error(
                $sender,
                'Block index out of range.'
            );

            return true;
        }

        $mine->removeBlock($index);

        $this->plugin->getMineManager()->save(
            $mine->getName()
        );

        $this->success(
            $sender,
            'Block entry removed.'
        );

        return true;
    }

    private function blockList(
        CommandSender $sender,
        Mine $mine
    ): bool {
        $blocks = $mine->getBlocks();

        if ($blocks === []) {
            $this->info(
                $sender,
                "Mine '{$mine->getName()}' has no blocks yet."
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eBlocks of ' . $mine->getName() . ':'
            )
        );

        foreach (
            $blocks as $index => $entry
        ) {
            $sender->sendMessage(
                $this->prefixed(
                    '§f#' . $index . ' §8| §7'
                    . $entry->getName() . ' §8| §e'
                    . $entry->getPercent() . '%'
                )
            );
        }

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
     * Looks a mine up by name, reporting the miss to the sender.
     */
    private function resolveMine(
        CommandSender $sender,
        ?string $name
    ): ?Mine {
        $mine = $name !== null && $name !== ''
            ? $this->plugin->getMineManager()->get($name)
            : null;

        if ($mine === null) {
            $this->error(
                $sender,
                "No mine named '" . ($name ?? '') . "'."
            );

            return null;
        }

        return $mine;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/mine pos1 §7- select the first corner (look at a block)',
            '§e/mine pos2 §7- select the second corner',
            '§e/mine create <name> §7- create a mine from the selection',
            '§e/mine block add <mine> <block> <percent> §7- add a block',
            '§e/mine block remove <mine> <index> §7- remove a block',
            '§e/mine block list <mine> §7- list the block list',
            '§e/mine clearblocks <mine> §7- empty the block list',
            '§e/mine setinterval <mine> <seconds> §7- 0 disables auto reset',
            '§e/mine setlabel <mine> §7- move the hologram',
            '§e/mine reset <mine|all> §7- refill now',
            '§e/mine info <mine> §7- details',
            '§e/mine list §7- list all mines'
        ];

        $sender->sendMessage(
            $this->prefixed('§eSkyMineZ mines')
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