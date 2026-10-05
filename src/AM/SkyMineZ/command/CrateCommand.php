<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\crate\Crate;
use AM\SkyMineZ\crate\Key;
use AM\SkyMineZ\crate\Reward;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;

/**
 * /crate - creates crates, hands out keys and edits the reward list.
 *
 * Every subcommand takes a crate *name*; the crate's world position is stored on
 * creation and can be moved afterwards with `/crate move`.
 */
final class CrateCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'crate',
            'Manage SkyMineZ crates',
            '/crate <create|remove|move|list|givekey|open|reward> ...',
            ['crates']
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
            'create' => $this->handleCreate($sender, $args),
            'remove', 'delete' => $this->handleRemove($sender, $args),
            'move', 'tp' => $this->handleMove($sender, $args),
            'list' => $this->handleList($sender),
            'givekey', 'key' => $this->handleGiveKey($sender, $args),
            'open' => $this->handleOpen($sender, $args),
            'reward' => $this->handleReward($sender, $args),
            'save' => $this->handleSave($sender),
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

                if ($name === null || $name === '') {
                    $this->error(
                        $player,
                        'Usage: /crate create <name>'
                    );

                    return;
                }

                if (!self::isValidName($name)) {
                    $this->error(
                        $player,
                        'A crate name may only contain letters, digits, '
                        . 'underscores and dashes.'
                    );

                    return;
                }

                $position = TargetResolver::lookedAtPosition(
                    $player
                );

                if ($position === null) {
                    $this->error(
                        $player,
                        'Look at a block first.'
                    );

                    return;
                }

                $crate = $this->plugin->getCrateManager()->create(
                    $name,
                    $position,
                    $position->getWorld()
                );

                /*
                 * A fresh crate accepts its own key id straight away, so the loop
                 * "create -> give key -> open" works without any extra setup.
                 */
                $crate->addKey($name);

                $this->plugin->getCrateManager()->save(
                    $name
                );

                $this->success(
                    $player,
                    "Created crate '{$name}' and registered its key."
                );

                $player->sendMessage(
                    $this->prefixed(
                        '§7Give yourself a key with /crate givekey <player> ' . $name
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
                'Usage: /crate remove <name>'
            );

            return true;
        }

        try {
            $removed = $this->plugin->getCrateManager()->remove(
                $name
            );
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $removed
            ? $this->success(
                $sender,
                "Removed crate '{$name}'."
            )
            : $this->error(
                $sender,
                "No crate named '{$name}'."
            );

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
                        'Usage: /crate move <name>'
                    );

                    return;
                }

                $crate = $this->plugin->getCrateManager()->getCrate(
                    $name
                );

                if ($crate === null) {
                    $this->error(
                        $player,
                        "No crate named '{$name}'."
                    );

                    return;
                }

                $position = TargetResolver::lookedAtPosition(
                    $player
                );

                if ($position === null) {
                    $this->error(
                        $player,
                        'Look at a block first.'
                    );

                    return;
                }

                /*
                 * Moving is a remove plus a create so the position index stays
                 * correct; doing it by hand would leave a stale entry behind and
                 * the old block would stop being protected.
                 */
                $this->plugin->getCrateManager()->remove(
                    $name
                );

                $this->plugin->getCrateManager()->create(
                    $name,
                    $position,
                    $position->getWorld()
                );

                $recreated = $this->plugin->getCrateManager()->getCrate(
                    $name
                );

                if ($recreated !== null) {
                    foreach (
                        $crate->getKeys() as $keyId
                    ) {
                        $recreated->addKey($keyId);
                    }

                    foreach (
                        $crate->getRewards() as $reward
                    ) {
                        $recreated->addRewardObject(
                            $reward
                        );
                    }
                }

                $this->plugin->getCrateManager()->save(
                    $name
                );

                $this->success(
                    $player,
                    "Moved crate '{$name}'."
                );
            }
        );
    }

    private function handleList(
        CommandSender $sender
    ): bool {
        $manager = $this->plugin->getCrateManager();

        if ($manager->count() === 0) {
            $this->info(
                $sender,
                'No crates are configured.'
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eCrates (' . $manager->count() . '):'
            )
        );

        foreach (
            $manager->getCrates() as $name => $crate
        ) {
            $position = $crate->getPosition();

            $sender->sendMessage(
                $this->prefixed(
                    '§f' . $name . ' §8| §7'
                    . $crate->getWorld()->getFolderName()
                    . ' §8('
                    . $position->getFloorX() . ', '
                    . $position->getFloorY() . ', '
                    . $position->getFloorZ() . ') §8| §7'
                    . count($crate->getRewards()) . ' rewards §8| §7'
                    . count($crate->getKeys()) . ' keys'
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleGiveKey(
        CommandSender $sender,
        array $args
    ): bool {
        $target = $this->plugin->getServer()->getPlayerExact(
            $args[1] ?? ''
        );

        $crateName = $args[2] ?? null;

        if ($target === null) {
            $this->error(
                $sender,
                'That player is not online.'
            );

            return true;
        }

        if ($crateName === null) {
            $this->error(
                $sender,
                'Usage: /crate givekey <player> <crate> [amount]'
            );

            return true;
        }

        $crate = $this->plugin->getCrateManager()->getCrate(
            $crateName
        );

        if ($crate === null) {
            $this->error(
                $sender,
                "No crate named '{$crateName}'."
            );

            return true;
        }

        $amount = max(
            1,
            min(
                64,
                (int) ($args[3] ?? 1)
            )
        );

        $keyId = $crate->getKeys()[0] ?? $crate->getName();

        $target->getInventory()->addItem(
            Key::create(
                $keyId,
                "§d" . $crate->getName() . " Key",
                $amount
            )
        );

        $this->success(
            $sender,
            "Gave {$amount}x '{$keyId}' key to " . $target->getName() . '.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleOpen(
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
                        'Usage: /crate open <name>'
                    );

                    return;
                }

                $crate = $this->plugin->getCrateManager()->getCrate(
                    $name
                );

                if ($crate === null) {
                    $this->error(
                        $player,
                        "No crate named '{$name}'."
                    );

                    return;
                }

                if (!$crate->showPreview($player)) {
                    return;
                }

                $this->info(
                    $player,
                    "Previewing crate '{$name}'. Sneak and right-click it to open."
                );
            }
        );
    }

    private function handleSave(
        CommandSender $sender
    ): bool {
        try {
            $this->plugin->getCrateManager()->saveAll();
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->success(
            $sender,
            'Saved all crates.'
        );

        return true;
    }

    /**
     * /crate reward <add|remove|list|weight|type> ...
     *
     * @param list<string> $args
     */
    private function handleReward(
        CommandSender $sender,
        array $args
    ): bool {
        $action = strtolower(
            $args[1] ?? 'list'
        );

        $crateName = $args[2] ?? null;

        if ($crateName === null) {
            $this->error(
                $sender,
                'Usage: /crate reward <add|remove|list|weight|type> <crate> ...'
            );

            return true;
        }

        $crate = $this->plugin->getCrateManager()->getCrate(
            $crateName
        );

        if ($crate === null) {
            $this->error(
                $sender,
                "No crate named '{$crateName}'."
            );

            return true;
        }

        switch ($action) {
            case 'add':
                return $this->rewardAdd(
                    $sender,
                    $crate,
                    $args
                );

            case 'remove':
                return $this->rewardRemove(
                    $sender,
                    $crate,
                    $args
                );

            case 'list':
                return $this->rewardList(
                    $sender,
                    $crate
                );

            case 'weight':
                return $this->rewardWeight(
                    $sender,
                    $crate,
                    $args
                );

            case 'type':
                return $this->rewardType(
                    $sender,
                    $crate,
                    $args
                );

            default:
                $this->error(
                    $sender,
                    'Unknown action. Use add, remove, list, weight or type.'
                );

                return true;
        }
    }

    /**
     * @param list<string> $args
     */
    private function rewardAdd(
        CommandSender $sender,
        Crate $crate,
        array $args
    ): bool {
        $itemSpec = $args[3] ?? null;

        if ($itemSpec === null) {
            $this->error(
                $sender,
                'Usage: /crate reward add <crate> <item[:meta][:count]> '
                . '[type] [weight]'
            );

            return true;
        }

        $item = self::parseItem(
            $itemSpec
        );

        if ($item === null) {
            $this->error(
                $sender,
                "Unknown item '{$itemSpec}'. Try /give style syntax, "
                . 'e.g. diamond_sword or diamond 64.'
            );

            return true;
        }

        $type = $args[4] ?? Reward::TYPE_COMMON;

        try {
            $weight = isset($args[5]) && is_numeric($args[5])
                ? (float) $args[5]
                : Reward::getDefaultWeightForType($type);
        } catch (\InvalidArgumentException) {
            $this->error(
                $sender,
                "Unknown reward type '{$type}'. Use "
                . implode(
                    ', ',
                    array_keys(Reward::types())
                ) . '.'
            );

            return true;
        }

        try {
            $crate->addRewardByType(
                $item,
                $type
            );

            $index = count(
                $crate->getRewards()
            ) - 1;

            if ($weight > 0.0) {
                $crate->setRewardWeight(
                    $index,
                    $weight
                );
            }
        } catch (\InvalidArgumentException $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->plugin->getCrateManager()->save(
            $crate->getName()
        );

        $this->success(
            $sender,
            "Added {$item->getCount()}x {$item->getName()} to '"
            . $crate->getName() . "' as reward #{$index}."
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function rewardRemove(
        CommandSender $sender,
        Crate $crate,
        array $args
    ): bool {
        $index = isset($args[3]) && is_numeric($args[3])
            ? (int) $args[3]
            : -1;

        if (
            $index < 0
            || !isset($crate->getRewards()[$index])
        ) {
            $this->error(
                $sender,
                'Reward index out of range.'
            );

            return true;
        }

        $crate->removeReward($index);

        $this->plugin->getCrateManager()->save(
            $crate->getName()
        );

        $this->success(
            $sender,
            'Removed reward.'
        );

        return true;
    }

    private function rewardList(
        CommandSender $sender,
        Crate $crate
    ): bool {
        $rewards = $crate->getRewards();

        if ($rewards === []) {
            $this->info(
                $sender,
                "Crate '{$crate->getName()}' has no rewards."
            );

            return true;
        }

        $sender->sendMessage(
            $this->prefixed(
                '§eRewards of ' . $crate->getName() . ':'
            )
        );

        foreach (
            $rewards as $index => $reward
        ) {
            $item = $reward->getItem();

            $sender->sendMessage(
                $this->prefixed(
                    '§f#' . $index
                    . ' §8| §7' . $reward->getType()
                    . ' §8| §f' . $item->getCount() . 'x '
                    . $item->getName()
                    . ' §8| §7chance §e'
                    . NumberFormatter::trim(
                        $crate->getRewardChance(
                            $index
                        ) ?? 0.0
                    ) . '% §8| §7weight §e'
                    . NumberFormatter::trim(
                        $reward->getWeight()
                    )
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function rewardWeight(
        CommandSender $sender,
        Crate $crate,
        array $args
    ): bool {
        $index = isset($args[3]) && is_numeric($args[3])
            ? (int) $args[3]
            : -1;
        $weight = isset($args[4]) && is_numeric($args[4])
            ? (float) $args[4]
            : -1.0;

        if (
            $index < 0
            || $weight <= 0.0
        ) {
            $this->error(
                $sender,
                'Usage: /crate reward weight <crate> <index> <weight>'
            );

            return true;
        }

        try {
            $crate->setRewardWeight(
                $index,
                $weight
            );
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->plugin->getCrateManager()->save(
            $crate->getName()
        );

        $this->success(
            $sender,
            'Weight updated.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function rewardType(
        CommandSender $sender,
        Crate $crate,
        array $args
    ): bool {
        $index = isset($args[3]) && is_numeric($args[3])
            ? (int) $args[3]
            : -1;
        $type = $args[4] ?? '';

        if (
            $index < 0
            || $type === ''
        ) {
            $this->error(
                $sender,
                'Usage: /crate reward type <crate> <index> <type>'
            );

            return true;
        }

        try {
            $crate->setRewardType(
                $index,
                $type
            );
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->plugin->getCrateManager()->save(
            $crate->getName()
        );

        $this->success(
            $sender,
            'Type updated.'
        );

        return true;
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/crate create <name> §7- create a crate on the block you look at',
            '§e/crate remove <name> §7- delete a crate',
            '§e/crate move <name> §7- move a crate to the block you look at',
            '§e/crate list §7- list all crates',
            '§e/crate givekey <player> <crate> [amount] §7- give a key',
            '§e/crate open <name> §7- preview a crate',
            '§e/crate reward add <crate> <item> [type] [weight] §7- add a reward',
            '§e/crate reward remove <crate> <index> §7- remove a reward',
            '§e/crate reward list <crate> §7- list rewards and their chances',
            '§e/crate reward weight <crate> <index> <weight> §7- change a weight',
            '§e/crate reward type <crate> <index> <type> §7- change a rarity',
            '§e/crate save §7- write crates.json now'
        ];

        $sender->sendMessage(
            $this->prefixed(
                '§eSkyMineZ crates'
            )
        );

        foreach (
            $lines as $line
        ) {
            $sender->sendMessage(
                $this->prefixed(
                    $line
                )
            );
        }

        return true;
    }

    /**
     * Parses `/give` style syntax, with or without metadata.
     */
    public static function parseItem(
        string $spec
    ): ?Item {
        try {
            $item = StringToItemParser::getInstance()->parse(
                $spec
            );
        } catch (\Throwable) {
            return null;
        }

        if ($item === null || $item->isNull()) {
            return null;
        }

        return $item;
    }

    private static function isValidName(
        string $name
    ): bool {
        return preg_match(
            '/^[A-Za-z0-9_-]{1,32}$/',
            $name
        ) === 1;
    }
}