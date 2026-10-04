<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use pocketmine\player\Player;
use pocketmine\world\Position;
use InvalidArgumentException;

final class Leaderboard
{
    public const TYPE_MONEY = 'money';
    public const TYPE_GOLD = 'gold';
    public const TYPE_MINED = 'mined';
    public const TYPE_DEATHS = 'deaths';
    public const TYPE_KILLS = 'kills';

    private const COLORS = [
        self::TYPE_MONEY => '§a',
        self::TYPE_GOLD => '§6',
        self::TYPE_MINED => '§d',
        self::TYPE_DEATHS => '§c',
        self::TYPE_KILLS => '§b'
    ];

    private const TITLES = [
        self::TYPE_MONEY => 'MONEY TOP',
        self::TYPE_GOLD => 'GOLD TOP',
        self::TYPE_MINED => 'MINED TOP',
        self::TYPE_DEATHS => 'DEATHS TOP',
        self::TYPE_KILLS => 'KILLS TOP'
    ];

    private MultiLineTextParticle $text;

    private ?string $lastHash = null;

    public function __construct(
        private string $name,
        private string $type,
        private Position $position,
        ?string $title = null
    ) {
        if (!isset(self::COLORS[$type])) {
            throw new InvalidArgumentException(
                "Unknown leaderboard type: {$type}"
            );
        }

        $this->text = new MultiLineTextParticle(
            $position->add(0.5, 2.5, 0.5),
            $position->getWorld()
        );

        $this->title = $title
            ?? self::TITLES[$type];
    }

    private string $title;

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(
        string $title
    ): self {
        $this->title = $title;

        return $this;
    }

    public function getColor(): string
    {
        return self::COLORS[$this->type];
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getWorld()
    {
        return $this->position->getWorld();
    }

    public function getText(): MultiLineTextParticle
    {
        return $this->text;
    }

    /**
     * @param list<array{name: string, value: int|float}> $rows
     */
    public function update(
        array $rows,
        string $serverAddress
    ): void {
        $lines = [];

        $color = $this->getColor();

        $lines[] =
            $color .
            $this->title;

        $lines[] = '§f';

        foreach ($rows as $index => $row) {
            $rank = $index + 1;

            $lines[] =
                '§e' .
                $rank .
                '. §f' .
                $row['name'] .
                ' §7- ' .
                $color .
                self::formatNumber(
                    $row['value']
                );
        }

        if ($rows === []) {
            $lines[] = '§8No ranked players';
        }

        $lines[] = '§f';

        $lines[] =
            '§7SERVER.IP §f' .
            $serverAddress;

        $hash = md5(
            implode("\n", $lines)
        );

        if ($hash === $this->lastHash) {
            return;
        }

        $this->lastHash = $hash;

        $this->text->setLines(
            $lines
        );
    }

    public function spawn(): void
    {
        $this->text->spawn();
    }

    public function spawnTo(
        Player $player
    ): void {
        if (
            $player->getWorld() !==
            $this->getWorld()
        ) {
            return;
        }

        $this->text->spawn(
            $player
        );
    }

    public function despawn(): void
    {
        $this->text->deSpawn();
    }

    public function isSpawned(): bool
    {
        return $this->text->isSpawned();
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'world' =>
                $this->position
                    ->getWorld()
                    ->getFolderName(),
            'x' => $this->position->x,
            'y' => $this->position->y,
            'z' => $this->position->z
        ];
    }

    public static function formatNumber(
        int|float $number
    ): string {
        $negative = $number < 0;

        $number = abs(
            (float) $number
        );

        if ($number < 1000) {
            $result = number_format(
                $number,
                0,
                '.',
                ''
            );

            return $negative
                ? '-' . $result
                : $result;
        }

        $suffixes = [
            'K',
            'M',
            'B',
            'T',
            'Qa',
            'Qi',
            'Sx',
            'Sp',
            'Oc',
            'No',
            'Dc',
            'Ud',
            'Dd',
            'Td',
            'Qad',
            'Qid',
            'Sxd',
            'Spd',
            'Ocd',
            'Nod'
        ];

        $index = 0;

        while (
            $number >= 1000 &&
            $index < count($suffixes) - 1
        ) {
            $number /= 1000;
            ++$index;
        }

        if (
            $index === count($suffixes) - 1 &&
            $number >= 1000
        ) {
            $result = sprintf(
                '%.2e',
                $number
            );

            return ($negative ? '-' : '') .
                $result;
        }

        $result = rtrim(
            rtrim(
                number_format(
                    $number,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );

        return ($negative ? '-' : '') .
            $result .
            $suffixes[$index];
    }
}