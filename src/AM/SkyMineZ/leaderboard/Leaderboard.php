<?php

declare(strict_types=1);

namespace AM\SkyMineZ\leaderboard;

use AM\SkyMineZ\useless\MultiLineTextParticle;
use AM\SkyMineZ\useless\NumberFormatter;
use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * A floating top-10 board for one statistic.
 *
 * The rendered lines are hashed so {@link update()} can skip the respawn when
 * nothing changed. Without that, every refresh would despawn and respawn every
 * line and make the board flicker for everyone watching.
 */
final class Leaderboard
{
    public const TYPE_MONEY = 'money';
    public const TYPE_GOLD = 'gold';
    public const TYPE_MINED = 'mined';
    public const TYPE_DEATHS = 'deaths';
    public const TYPE_KILLS = 'kills';
    public const TYPE_TEAM_LEVEL = 'team_level';
    public const TYPE_TEAM_WINS = 'team_wins';

    /**
     * @var array<string, string>
     */
    private const COLORS = [
        self::TYPE_MONEY => '§a',
        self::TYPE_GOLD => '§6',
        self::TYPE_MINED => '§d',
        self::TYPE_DEATHS => '§c',
        self::TYPE_KILLS => '§b',
        self::TYPE_TEAM_LEVEL => '§e',
        self::TYPE_TEAM_WINS => '§6'
    ];

    /**
     * @var array<string, string>
     */
    private const TITLES = [
        self::TYPE_MONEY => '§aMONEY TOP',
        self::TYPE_GOLD => '§6GOLD TOP',
        self::TYPE_MINED => '§dMINED TOP',
        self::TYPE_DEATHS => '§cDEATHS TOP',
        self::TYPE_KILLS => '§bKILLS TOP',
        self::TYPE_TEAM_LEVEL => '§eTEAM LEVEL TOP',
        self::TYPE_TEAM_WINS => '§6TEAM WINS TOP'
    ];

    /**
     * Height of the hologram above the anchor, so the title sits on top of the
     * list instead of below it.
     */
    private const LIST_ROWS = 10;

    private MultiLineTextParticle $text;

    private string $title;

    private ?string $lastHash = null;

    /**
     * @throws InvalidArgumentException when the type is unknown
     */
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

        $this->title = $title
            ?? self::TITLES[$type];

        $this->text = new MultiLineTextParticle(
            new Vector3(
                $position->x,
                $position->y + (
                    (self::LIST_ROWS + 1) * MultiLineTextParticle::LINE_SPACING
                ),
                $position->z
            ),
            $position->getWorld()
        );
    }

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

    public function setPosition(
        Position $position
    ): self {
        $this->position = $position;

        $this->text->setPosition(
            new Vector3(
                $position->x,
                $position->y + (
                    (self::LIST_ROWS + 1) * MultiLineTextParticle::LINE_SPACING
                ),
                $position->z
            ),
            $position->getWorld()
        );

        /*
         * The position changed, so the cached hash no longer proves the text is
         * already on screen where it needs to be.
         */
        $this->lastHash = null;

        return $this;
    }

    public function getWorld(): World
    {
        return $this->position->getWorld();
    }

    public function getText(): MultiLineTextParticle
    {
        return $this->text;
    }

    /**
     * Every supported type, for command completion and validation.
     *
     * @return list<string>
     */
    public static function getTypes(): array
    {
        return array_keys(self::COLORS);
    }

    public static function isValidType(
        string $type
    ): bool {
        return isset(self::COLORS[$type]);
    }

    /**
     * Re-renders the board.
     *
     * @param list<array{name: string, value: int|float}> $rows highest first
     */
    public function update(
        array $rows,
        string $serverAddress
    ): void {
        $lines = [];

        $color = $this->getColor();

        $lines[] = $this->title;
        $lines[] = '§f';

        if ($rows === []) {
            $lines[] = '§8No ranked players';
        } else {
            foreach (
                $rows as $index => $row
            ) {
                $lines[] =
                    '§e' . ($index + 1) . '. §f'
                    . $row['name']
                    . ' §7- '
                    . $color
                    . NumberFormatter::short($row['value']);
            }
        }

        $lines[] = '§f';
        $lines[] = '§7SERVER.IP §f' . $serverAddress;

        $hash = md5(implode("\n", $lines));

        if ($hash === $this->lastHash) {
            return;
        }

        $this->lastHash = $hash;

        $this->text->setLines($lines);
    }

    public function spawn(): void
    {
        $this->text->spawn();
    }

    public function spawnTo(
        Player $player
    ): void {
        if (
            $this->getWorld() !== $player->getWorld()
        ) {
            return;
        }

        $this->text->spawn($player);
    }

    public function despawn(): void
    {
        $this->text->deSpawn();
    }

    public function isSpawned(): bool
    {
        return $this->text->isSpawned();
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     world: string,
     *     x: float,
     *     y: float,
     *     z: float
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'world' => $this->getWorld()->getFolderName(),
            'x' => $this->position->x,
            'y' => $this->position->y,
            'z' => $this->position->z
        ];
    }
}