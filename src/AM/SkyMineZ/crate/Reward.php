<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;

final class Reward
{
    public const TYPE_COMMON = 'common';
    public const TYPE_UNCOMMON = 'uncommon';
    public const TYPE_RARE = 'rare';
    public const TYPE_EPIC = 'epic';
    public const TYPE_LEGENDARY = 'legendary';

    private const TYPE_DEFAULT_WEIGHTS = [
        self::TYPE_COMMON => 100.0,
        self::TYPE_UNCOMMON => 25.0,
        self::TYPE_RARE => 5.0,
        self::TYPE_EPIC => 1.0,
        self::TYPE_LEGENDARY => 0.25
    ];

    public function __construct(
        private Item $item,
        private float $weight,
        private string $type = self::TYPE_COMMON
    ) {
        if ($weight <= 0) {
            throw new InvalidArgumentException(
                'Reward weight must be greater than 0.'
            );
        }

        if ($type === '') {
            throw new InvalidArgumentException(
                'Reward type cannot be empty.'
            );
        }
    }

    public static function getDefaultWeightForType(
        string $type
    ): float {
        if (!isset(self::TYPE_DEFAULT_WEIGHTS[$type])) {
            throw new InvalidArgumentException(
                "Unknown reward type: {$type}"
            );
        }

        return self::TYPE_DEFAULT_WEIGHTS[$type];
    }

    /**
     * @return array<string, float> every rarity with its default weight
     */
    public static function types(): array
    {
        return self::TYPE_DEFAULT_WEIGHTS;
    }

    public function getItem(): Item
    {
        return clone $this->item;
    }

    public function setItem(Item $item): self
    {
        $this->item = clone $item;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): self
    {
        if ($weight <= 0) {
            throw new InvalidArgumentException(
                'Reward weight must be greater than 0.'
            );
        }

        $this->weight = $weight;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if ($type === '') {
            throw new InvalidArgumentException(
                'Reward type cannot be empty.'
            );
        }

        $this->type = $type;

        return $this;
    }

    public function getChancePercent(
        float $totalWeight
    ): float {
        if ($totalWeight <= 0) {
            return 0.0;
        }

        return ($this->weight / $totalWeight) * 100;
    }

    /**
     * @return array{item: string, weight: float, type: string}
     */
    public function toArray(): array
    {
        $serializer = new LittleEndianNbtSerializer();

        $binary = $serializer->write(
            new TreeRoot(
                $this->item->nbtSerialize()
            )
        );

        return [
            'item' => base64_encode($binary),
            'weight' => $this->weight,
            'type' => $this->type
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data
    ): ?self {
        if (
            !isset(
                $data['item'],
                $data['weight']
            )
        ) {
            return null;
        }

        $encoded = $data['item'];

        if (!is_string($encoded)) {
            return null;
        }

        $binary = base64_decode(
            $encoded,
            true
        );

        if ($binary === false) {
            return null;
        }

        try {
            $root = (new LittleEndianNbtSerializer())
                ->read($binary);

            $compound = $root->mustGetCompoundTag();

            $item = Item::safeNbtDeserialize(
                $compound,
                'SkyMineZ crate reward'
            );
        } catch (\Throwable) {
            return null;
        }

        if ($item->isNull()) {
            return null;
        }

        if (!is_numeric($data['weight'])) {
            return null;
        }

        $weight = (float) $data['weight'];

        if ($weight <= 0) {
            return null;
        }

        $type = isset($data['type']) && is_string($data['type'])
            ? $data['type']
            : self::TYPE_COMMON;

        return new self(
            $item,
            $weight,
            $type
        );
    }
}