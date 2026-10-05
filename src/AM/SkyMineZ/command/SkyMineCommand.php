<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\economy\BaseEconomy;
use AM\SkyMineZ\economy\EconomyChangeEventReason;
use AM\SkyMineZ\lagmaker\LagMaker;
use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\NumberFormatter;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /skymine - the umbrella command: player settings, the admin utilities and the
 * main menu form.
 *
 * Everything a player needs (PvP toggle, sidebar toggle, stats) is reachable
 * without a permission, because it only ever affects their own session.
 */
final class SkyMineCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'skymine',
            'SkyMineZ main command',
            '/skymine <menu|pvp|hud|stats|money|gold|pos1|pos2|reload|lagmaker|save> ...',
            ['skyminesz', 'smz'],
            Main::PERMISSION_USE
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
        $sub = strtolower(
            $args[0] ?? 'menu'
        );

        /*
         * Player-facing subcommands work without any permission; the admin ones
         * check their own.
         */
        return match ($sub) {
            'menu' => $this->handleMenu($sender),
            'pvp' => $this->handlePvp($sender, $args),
            'hud', 'sidebar' => $this->handleHud($sender),
            'stats' => $this->handleStats($sender, $args),
            'money' => $this->handleEconomy(
                $sender,
                $args,
                $this->plugin->getMoneyEconomy()
            ),
            'gold' => $this->handleEconomy(
                $sender,
                $args,
                $this->plugin->getGoldEconomy()
            ),
            'pos1' => $this->handlePosition($sender, $args, 'pos1'),
            'pos2' => $this->handlePosition($sender, $args, 'pos2'),
            'reset' => $this->handleResetSelection($sender),
            'reload' => $this->handleReload($sender),
            'lagmaker' => $this->handleLagMaker($sender, $args),
            'save' => $this->handleSave($sender),
            default => $this->handleHelp($sender)
        };
    }

    private function handleMenu(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error(
                $sender,
                'The menu is only available in-game.'
            );

            return true;
        }

        (new MainMenuForm(
            $this->plugin
        ))->send($sender);

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handlePvp(
        CommandSender $sender,
        array $args
    ): bool {
        $requested = strtolower(
            $args[1] ?? 'toggle'
        );

        /*
         * PvP lives in a live session array, so the target has to be online.
         */
        $target = ($args[1] ?? null) !== null && !in_array(
            $requested,
            ['on', 'off', 'toggle', 'true', 'false'],
            true
        )
            ? $this->plugin->getServer()->getPlayerExact($requested)
            : ($sender instanceof Player ? $sender : null);

        if ($target === null) {
            $this->error(
                $sender,
                'That player is not online.'
            );

            return true;
        }

        $manager = $this->plugin->getPvpManager();

        if (!$this->testPermission($sender)) {
            $newState = $manager->toggle(
                $target->getName()
            );

            $this->success(
                $target,
                'PvP is now ' . ($newState ? 'ON' : 'OFF') . '.'
            );

            return true;
        }

        $state = match ($requested) {
            'on', 'true' => true,
            'off', 'false' => false,
            default => !$manager->getState($target->getName())
        };

        $result = $manager->setState(
            $target->getName(),
            $state
        );

        $this->success(
            $sender,
            'PvP for ' . $target->getName() . ' is '
            . ($result ? 'ON' : 'OFF') . '.'
        );

        return true;
    }

    private function handleHud(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error(
                $sender,
                'The sidebar can only be toggled in-game.'
            );

            return true;
        }

        $enabled = $this->plugin->getScoreHud()->toggle(
            $sender
        );

        $this->success(
            $sender,
            'Sidebar ' . ($enabled ? 'enabled' : 'disabled') . '.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleStats(
        CommandSender $sender,
        array $args
    ): bool {
        $targetName = $this->resolveStatsTarget(
            $sender,
            $args[1] ?? null
        );

        if ($targetName === null) {
            return true;
        }

        $miner = $this->plugin->getMinerManager()->getOrLoad(
            $targetName
        );

        $sender->sendMessage(
            $this->prefixed(
                '§eStats of ' . $targetName
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Mined: §f' . NumberFormatter::short(
                    $miner->getMined()
                )
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Deaths: §f' . NumberFormatter::short(
                    $miner->getDeaths()
                )
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Kills: §f' . NumberFormatter::short(
                    $miner->getKills()
                )
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Kill streak: §f' . $miner->getKillStreak()
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Money: §f' . NumberFormatter::short(
                    $this->plugin->getMoneyEconomy()->get(
                        $targetName
                    )
                )
            )
        );
        $sender->sendMessage(
            $this->prefixed(
                '§7Gold: §f' . NumberFormatter::short(
                    $this->plugin->getGoldEconomy()->get(
                        $targetName
                    )
                )
            )
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleEconomy(
        CommandSender $sender,
        array $args,
        BaseEconomy $economy
    ): bool {
        if (!$this->testPermission($sender)) {
            $this->error(
                $sender,
                'You do not have permission to manage balances.'
            );

            return true;
        }

        $action = strtolower(
            $args[1] ?? 'check'
        );

        $targetName = $args[2] ?? (
            $sender instanceof Player
                ? $sender->getName()
                : null
        );

        if ($targetName === null) {
            $this->error(
                $sender,
                'Usage: /' . $economy->getType() . ' <give|take|set|check> <player> [amount]'
            );

            return true;
        }

        $current = $economy->get($targetName);

        if ($action === 'check') {
            $this->success(
                $sender,
                $targetName . ' has ' . NumberFormatter::short($current)
                . ' ' . $economy->getType() . '.'
            );

            return true;
        }

        $amount = isset($args[3]) && is_numeric($args[3])
            ? (int) $args[3]
            : 0;

        if ($amount <= 0) {
            $this->error(
                $sender,
                'The amount must be greater than 0.'
            );

            return true;
        }

        $reason = EconomyChangeEventReason::COMMAND;

        $new = match ($action) {
            'give', 'add' => $economy->add(
                $targetName,
                $amount,
                $reason
            ),
            'take', 'remove' => $economy->reduce(
                $targetName,
                $amount,
                $reason
            ),
            'set' => $this->setBalance(
                $economy,
                $targetName,
                $amount,
                $reason
            ),
            default => -1
        };

        if ($new < 0) {
            $this->error(
                $sender,
                'Use give, take, set or check.'
            );

            return true;
        }

        $this->success(
            $sender,
            $targetName . ' now has ' . NumberFormatter::short($new)
            . ' ' . $economy->getType() . '.'
        );

        $player = $this->plugin->getServer()->getPlayerExact(
            $targetName
        );

        if (
            $player !== null
            && !$sender->hasPermission(
                Main::PERMISSION_ADMIN
            )
        ) {
            $player->sendMessage(
                $this->prefixed(
                    '§aYour ' . $economy->getType() . ' balance changed to '
                    . NumberFormatter::short($new) . '.'
                )
            );
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handlePosition(
        CommandSender $sender,
        array $args,
        string $which
    ): bool {
        if (!$sender instanceof Player) {
            $this->error(
                $sender,
                'Selections can only be set in-game.'
            );

            return true;
        }

        $position = TargetResolver::lookedAtPosition(
            $sender
        ) ?? $sender->getPosition();

        $selection = $this->plugin->getSelectionManager();

        if ($which === 'pos1') {
            $selection->setPos1(
                $sender,
                $position
            );
        } else {
            $selection->setPos2(
                $sender,
                $position
            );
        }

        $this->success(
            $sender,
            $which . ' set to ' . $position->getWorld()->getFolderName()
            . ' (' . $position->getFloorX() . ', '
            . $position->getFloorY() . ', '
            . $position->getFloorZ() . ')'
        );

        return true;
    }

    private function handleResetSelection(
        CommandSender $sender
    ): bool {
        if (!$sender instanceof Player) {
            $this->error(
                $sender,
                'Selections can only be cleared in-game.'
            );

            return true;
        }

        $this->plugin->getSelectionManager()->clear(
            $sender
        );

        $this->success(
            $sender,
            'Selection cleared.'
        );

        return true;
    }

    private function handleReload(
        CommandSender $sender
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        try {
            $this->plugin->reload();
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->success(
            $sender,
            'SkyMineZ reloaded.'
        );

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function handleLagMaker(
        CommandSender $sender,
        array $args
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        $lagMaker = $this->plugin->getLagMaker();

        $action = strtolower(
            $args[1] ?? 'status'
        );

        if ($action === 'status') {
            $this->info(
                $sender,
                'Enabled: ' . ($lagMaker->isEnabled() ? 'yes' : 'no')
                . ' | mode: ' . $lagMaker->getMode()
                . ' | auto stack: '
                . ($this->plugin->getConfigManager()->getBool(
                    'lagmaker.auto-stack',
                    true
                ) ? 'yes' : 'no')
            );

            return true;
        }

        if ($action === 'toggle') {
            $enabled = !$lagMaker->isEnabled();

            $lagMaker->setEnabled($enabled);

            $this->success(
                $sender,
                'Lag protection ' . ($enabled ? 'enabled' : 'disabled') . '.'
            );

            return true;
        }

        if ($action === 'cleanup') {
            $mode = strtolower(
                $args[2] ?? ''
            );

            if (
                !in_array(
                    $mode,
                    [
                        LagMaker::MODE_OFF,
                        LagMaker::MODE_TTL,
                        LagMaker::MODE_ALL
                    ],
                    true
                )
            ) {
                $this->error(
                    $sender,
                    'Mode must be off, ttl or all.'
                );

                return true;
            }

            $this->plugin->getConfigManager()->set(
                'lagmaker.cleanup.mode',
                $mode
            );
            $this->plugin->getConfigManager()->save();

            $this->success(
                $sender,
                "Cleanup mode set to '{$mode}'."
            );

            return true;
        }

        $this->error(
            $sender,
            'Use /skymine lagmaker <status|toggle|cleanup <mode>>'
        );

        return true;
    }

    private function handleSave(
        CommandSender $sender
    ): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        try {
            $this->plugin->getCrateManager()->saveAll();
            $this->plugin->getSlapperManager()->saveAll();
            $this->plugin->getLeaderboardManager()->saveAll();
            $this->plugin->getMineManager()->saveAll();
            $this->plugin->getOutpostManager()->saveAll();
        } catch (\Throwable $exception) {
            $this->fail(
                $sender,
                $exception
            );

            return true;
        }

        $this->success(
            $sender,
            'Saved every SkyMineZ store.'
        );

        return true;
    }

    /**
     * Resolves the player a stats query is about.
     *
     * Stats are read from the stored file, so an offline name is fine here;
     * unlike PvP, nothing about them needs a live session.
     */
    private function resolveStatsTarget(
        CommandSender $sender,
        ?string $name
    ): ?string {
        if (
            $name === null
            || $name === ''
        ) {
            if (!$sender instanceof Player) {
                $this->error(
                    $sender,
                    'Name a player when running this from the console.'
                );

                return null;
            }

            return $sender->getName();
        }

        return $name;
    }

    private function setBalance(
        BaseEconomy $economy,
        string $playerName,
        int $amount,
        string $reason
    ): int {
        $economy->set(
            $playerName,
            $amount,
            $reason
        );

        return $economy->get(
            $playerName
        );
    }

    private function handleHelp(
        CommandSender $sender
    ): bool {
        $lines = [
            '§e/skymine menu §7- open the main menu',
            '§e/skymine pvp [on|off] §7- toggle your PvP',
            '§e/skymine hud §7- toggle the sidebar',
            '§e/skymine stats [player] §7- show mining stats',
            '§e/skymine money <give|take|set|check> <player> [amount]',
            '§e/skymine gold <give|take|set|check> <player> [amount]',
            '§e/skymine pos1 §7- select the first corner',
            '§e/skymine pos2 §7- select the second corner',
            '§e/skymine lagmaker <status|toggle|cleanup <mode>>',
            '§e/skymine reload §7- re-read config.yml and reload the data',
            '§e/skymine save §7- write every store now'
        ];

        $sender->sendMessage(
            $this->prefixed('§eSkyMineZ')
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