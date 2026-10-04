<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;

final class Key
{
    private const TAG_KEY_ID = 'SkyMineZ_KeyId';

    public static function create(
        string $id,
        string $name
    ): Item {
        $item = VanillaBlocks::TRIPWIRE_HOOK()->asItem();

        $item->setCustomName($name);

        $item->addEnchantment(
            new EnchantmentInstance(
                VanillaEnchantments::UNBREAKING(),
                1
            )
        );

        $tag = $item->getNamedTag();

        $tag->setString(
            self::TAG_KEY_ID,
            $id
        );

        $item->setNamedTag($tag);

        return $item;
    }

    public static function isKey(Item $item): bool
    {
        return self::getId($item) !== null;
    }

    public static function getId(Item $item): ?string
    {
        $id = $item->getNamedTag()->getString(
            self::TAG_KEY_ID,
            ''
        );

        return $id !== '' ? $id : null;
    }

    public static function is(
        Item $item,
        string $id
    ): bool {
        return self::getId($item) === $id;
    }
}