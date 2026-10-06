<?php

declare(strict_types=1);

namespace AM\SkyMineZ\command;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\config\Messages;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

/**
 * /quest - the daily quest window.
 *
 * Quests themselves are defined in config.yml; this command only opens the UI
 * where players track progress and claim finished quests.
 */
final class QuestCommand extends BaseCommand
{
    public function __construct(
        Main $plugin
    ) {
        parent::__construct(
            $plugin,
            'quest',
            'View daily quests and claim rewards',
            '/quest',
            ['quests', 'questslist', 'daily'],
            Main::PERMISSION_USE
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

        if (!$sender instanceof Player) {
            $this->error($sender, Messages::get($this->plugin, Messages::QUEST_ONLY));

            return true;
        }

        $quests = $this->plugin->getQuestManager()->getQuests();

        if ($quests === []) {
            $this->info($sender, Messages::get($this->plugin, Messages::QUEST_NONE));

            return true;
        }

        (new \AM\SkyMineZ\quest\QuestMenuForm($this->plugin))->send($sender);

        return true;
    }
}