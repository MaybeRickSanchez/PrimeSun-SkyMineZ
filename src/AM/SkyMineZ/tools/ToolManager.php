<?php

declare(strict_types=1);

namespace AM\SkyMineZ\tools;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\Items;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Progression gear: unbreakable, undroppable tools/armor that earn XP from
 * breaking blocks and spend materials on level upgrades.
 *
 * Identity lives in NBT (tag + level + xp + unique id), so levels survive
 * relogs, deaths and server restarts with zero database. The unique id is
 * what makes upgrades safe: the confirm step re-reads the held item and
 * refuses anything that is not the exact piece that opened the UI.
 *
 * Only {@link Durable} items (tools, armor) can become gear — nothing else
 * has durability worth removing or enchantment slots worth filling.
 */
final class ToolManager
{
    public const TAG_GEAR = 'SkyMineZ_Gear';
    public const TAG_LEVEL = 'SkyMineZ_GearLevel';
    public const TAG_XP = 'SkyMineZ_GearXp';
    public const TAG_ID = 'SkyMineZ_GearId';

    public function __construct(
        private Main $main
    ) {
    }

    public static function isGear(
        Item $item
    ): bool {
        return $item->getNamedTag()->getByte(self::TAG_GEAR, 0) === 1;
    }

    public static function getLevel(
        Item $item
    ): int {
        return max(1, $item->getNamedTag()->getInt(self::TAG_LEVEL, 1));
    }

    public static function getXp(
        Item $item
    ): int {
        return max(0, $item->getNamedTag()->getInt(self::TAG_XP, 0));
    }

    public function maxLevel(): int
    {
        return max(
            1,
            $this->main->getConfigManager()->getInt('tools.max-level', 10)
        );
    }

    public function xpPerBlock(): int
    {
        return max(
            0,
            $this->main->getConfigManager()->getInt('tools.xp-per-block', 1)
        );
    }

    public function xpPerLevel(): int
    {
        return max(
            1,
            $this->main->getConfigManager()->getInt('tools.xp-per-level', 100)
        );
    }

    /**
     * Total XP a piece needs banked before it may become this level.
     */
    public function xpForLevel(
        int $level
    ): int {
        return max(0, ($level - 1)) * $this->xpPerLevel();
    }

    /**
     * Turns a normal tool/armor piece into level-1 progression gear.
     * Returns null when the item cannot become gear.
     */
    public function attune(
        Item $item
    ): ?Item {
        if (!$item instanceof Durable || $item->isNull()) {
            return null;
        }

        if (self::isGear($item)) {
            return $item;
        }

        $item = clone $item;
        $item->setUnbreakable(true);

        $tag = $item->getNamedTag();
        $tag->setByte(self::TAG_GEAR, 1);
        $tag->setInt(self::TAG_LEVEL, 1);
        $tag->setInt(self::TAG_XP, 0);
        $tag->setString(self::TAG_ID, bin2hex(random_bytes(16)));
        $item->setNamedTag($tag);

        return self::paintLore($item);
    }

    /**
     * Banks XP from breaking. Returns the new level when it went up.
     */
    public function addXp(
        Player $player,
        int $amount
    ): ?int {
        if ($amount <= 0) {
            return null;
        }

        $inventory = $player->getInventory();
        $held = $inventory->getItemInHand();

        if (!self::isGear($held)) {
            return null;
        }

        $before = self::getLevel($held);

        $tag = $held->getNamedTag();
        $tag->setInt(self::TAG_XP, self::getXp($held) + $amount);
        $held->setNamedTag($tag);

        $held = self::paintLore($held);
        $inventory->setItemInHand($held);

        $after = self::getLevel($held);

        return $after > $before ? $after : null;
    }

    /**
     * Upgrade package for reaching a level: enchantments granted and materials
     * consumed.
     *
     * @return array{enchants: list<array{id: string, level: int}>, materials: list<array{item: Item, count: int}>}
     */
    public function upgradeFor(
        int $level
    ): array {
        $enchants = [];
        $materials = [];

        foreach ($this->upgradeTable() as $entry) {
            if (($entry['level'] ?? 0) !== $level) {
                continue;
            }

            foreach ((array) ($entry['enchants'] ?? []) as $enchant => $enchantLevel) {
                if (is_string($enchant) && is_numeric($enchantLevel)) {
                    $enchants[] = ['id' => $enchant, 'level' => max(1, (int) $enchantLevel)];
                }
            }

            foreach ((array) ($entry['materials'] ?? []) as $material) {
                if (!is_array($material) || !isset($material['item']) || !is_string($material['item'])) {
                    continue;
                }

                $item = Items::parse($material['item']);

                if ($item === null) {
                    continue;
                }

                $count = isset($material['count']) && is_numeric($material['count'])
                    ? max(1, (int) $material['count'])
                    : 1;

                $item->setCount(1);

                $materials[] = ['item' => $item, 'count' => $count];
            }
        }

        return ['enchants' => $enchants, 'materials' => $materials];
    }

    /**
     * Applies the next-level upgrade to the held gear. Every requirement is
     * re-validated here — never trust the UI state, the player may have
     * swapped items, spent materials or XP since opening it.
     */
    public function upgrade(
        Player $player
    ): bool {
        $inventory = $player->getInventory();
        $held = $inventory->getItemInHand();

        if (!self::isGear($held)) {
            return false;
        }

        $level = self::getLevel($held);

        if ($level >= $this->maxLevel()) {
            return false;
        }

        $next = $level + 1;

        if (self::getXp($held) < $this->xpForLevel($next)) {
            return false;
        }

        $package = $this->upgradeFor($next);

        if ($package['enchants'] === [] && $package['materials'] === []) {
            return false;
        }

        foreach ($package['materials'] as $material) {
            if (Items::countOf($inventory, $material['item']) < $material['count']) {
                return false;
            }
        }

        foreach ($package['materials'] as $material) {
            $taken = Items::take($inventory, $material['item'], $material['count']);

            if ($taken < $material['count']) {
                // Single-threaded, so this cannot happen after the check
                // above; bail out rather than half-applying.
                return false;
            }
        }

        foreach ($package['enchants'] as $enchant) {
            $type = StringToEnchantmentParser::getInstance()->parse($enchant['id']);

            if ($type === null) {
                continue;
            }

            $held->addEnchantment(new EnchantmentInstance($type, $enchant['level']));
        }

        $tag = $held->getNamedTag();
        $tag->setInt(self::TAG_LEVEL, $next);
        $held->setNamedTag($tag);

        $held = self::paintLore($held);
        $inventory->setItemInHand($held);

        return true;
    }

    /**
     * @return list<array{level: int}>
     */
    private function upgradeTable(): array
    {
        $raw = $this->main->getConfigManager()->get('tools.upgrades', []);

        if (!is_array($raw)) {
            return [];
        }

        $table = [];

        foreach ($raw as $entry) {
            if (
                !is_array($entry)
                || !isset($entry['level'])
                || !is_numeric($entry['level'])
            ) {
                continue;
            }

            $table[] = [
                'level' => max(2, (int) $entry['level']),
                'enchants' => $entry['enchants'] ?? [],
                'materials' => $entry['materials'] ?? []
            ];
        }

        return $table;
    }

    private static function paintLore(
        Item $item
    ): Item {
        $level = self::getLevel($item);
        $xp = self::getXp($item);

        $item->setLore([
            '§7Progression gear §8| §eLv ' . $level,
            '§7XP: §f' . $xp
        ]);

        return $item;
    }
}