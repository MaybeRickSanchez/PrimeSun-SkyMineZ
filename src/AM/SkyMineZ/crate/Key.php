<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\enchantment\VanillaEnchantments;

/**
 * Crate keys.
 *
 * A key is a tripwire hook carrying an NBT string with its crate id. Storing the
 * id in the item itself means a key works across restarts and can be recognised
 * without a lookup table.
 */
final class Key
{
    private const TAG_KEY_ID = 'SkyMineZKeyId';

    /**
     * Builds a key item. The custom name is what players see in the hotbar, so
     * it is coloured by default.
     */
    public static function create(
        string $id,
        ?string $name = null,
        int $stackSize = 64
    ): Item {
        if ($id === '') {
            throw new \InvalidArgumentException(
                'A key id cannot be empty.'
            );
        }

        $item = VanillaBlocks::TRIPWIRE_HOOK()->asItem();
        $item->setCustomName(
            $name ?? "§d" . $id . " Key"
        );

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
        $item->setCount(max(
            1,
            min(
                $stackSize,
                $item->getMaxStackSize()
            )
        ));

        return $item;
    }

    /**
     * Crate id carried by an item, or null when the item is not a key.
     */
    public static function getId(Item $item): ?string
    {
        $id = $item->getNamedTag()->getString(
            self::TAG_KEY_ID,
            ''
        );

        return $id !== '' ? $id : null;
    }

    public static function isKey(Item $item): bool
    {
        return self::getId($item) !== null;
    }

    public static function is(
        Item $item,
        string $id
    ): bool {
        return self::getId($item) === $id;
    }

    /**
     * All crate ids this item could open. An item carries at most one, so this
     * exists to mirror the crate side of the API.
     *
     * @return list<string>
     */
    public static function getIds(Item $item): array
    {
        $id = self::getId($item);

        return $id === null ? [] : [$id];
    }
}