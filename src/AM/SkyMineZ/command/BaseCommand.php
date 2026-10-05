<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * Shared plumbing for every SkyMineZ command.
 *
 * Gives the subcommands a consistent reply format (prefixed, colour coded),
 * permission checks and the small parsing helpers that would otherwise be copied
 * into a dozen classes.
 */
abstract class BaseCommand extends Command
{
    public function __construct(
        protected Main $plugin,
        string $name,
        string $description,
        string $usage,
        array $aliases = [],
        string $permission = Main::PERMISSION_ADMIN
    ) {
        parent::__construct(
            $name,
            $description,
            $usage,
            $aliases
        );

        /*
         * PocketMine refuses to load a plugin whose commands have no permission
         * declared, and plugin.yml is the single place they are listed. Setting
         * it here too keeps Command::testPermission() and the manifest in sync.
         */
        $this->setPermission($permission);
    }

    /**
     * @param list<string> $args
     * @param callable(Player, list<string>): void $handler
     */
    protected function runPlayerSubCommand(
        CommandSender $sender,
        array $args,
        callable $handler
    ): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(
                $this->prefixed(
                    '§cThis command can only be used in-game.'
                )
            );

            return true;
        }

        $handler(
            $sender,
            $args
        );

        return true;
    }

    protected function prefixed(
        string $message
    ): string {
        return $this->plugin->getConfigManager()->getPrefix()
            . $message;
    }

    protected function success(
        CommandSender $sender,
        string $message
    ): void {
        $sender->sendMessage(
            $this->prefixed('§a' . $message)
        );
    }

    protected function error(
        CommandSender $sender,
        string $message
    ): void {
        $sender->sendMessage(
            $this->prefixed('§c' . $message)
        );
    }

    protected function info(
        CommandSender $sender,
        string $message
    ): void {
        $sender->sendMessage(
            $this->prefixed('§7' . $message)
        );
    }

    /**
     * Reports a caught exception without dumping a stack trace into chat, which
     * is unreadable in-game and leaks internals.
     */
    protected function fail(
        CommandSender $sender,
        \Throwable $exception
    ): void {
        $this->error(
            $sender,
            $exception->getMessage()
        );

        $this->plugin->getLogger()->logException(
            $exception
        );
    }

    /**
     * Reads the rest of the argument list as one string, for messages and
     * commands with spaces in them.
     *
     * @param list<string> $args
     */
    /**
     * Registry names (crates, mines, outposts, slappers): letters, digits,
     * underscore and dash, 1-32 chars. One canonical rule so a name accepted
     * by one command is never rejected by another.
     */
    protected static function isValidName(
        string $name
    ): bool {
        return preg_match(
            '/^[A-Za-z0-9_-]{1,32}$/',
            $name
        ) === 1;
    }

    /**
     * @param list<string> $args
     */
    protected function joinArguments(
        array $args,
        int $from = 1
    ): string {
        return implode(
            ' ',
            array_slice(
                $args,
                $from
            )
        );
    }

    /**
     * Positional selection marker, either the block the sender is looking at or
     * the block they stand on. Every "click a block" command accepts both.
     */
    protected function targetBlock(
        Player $player
    ): \pocketmine\world\Position {
        return $player->getPosition();
    }
}