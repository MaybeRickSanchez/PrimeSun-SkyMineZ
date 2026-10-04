<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\useless\ReadOnlyInventory;
use AM\SkyMineZ\useless\TextParticle;
use InvalidArgumentException;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\tile\Chest as ChestTile;
use pocketmine\color\Color;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\ItemBreakParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\world\World;

final class Crate
{
    private string $name;

    private Position $position;

    private TextParticle $textParticle;

    /**
     * @var array<string, true>
     */
    private array $keys = [];

    /**
     * @var array<int, Reward>
     */
    private array $rewards = [];

    /**
     * @var array<int, true>
     */
    private array $previewViewers = [];

    private ?int $openingPlayerId = null;

    private ?ItemEntity $floatingItem = null;

    private bool $busy = false;

    private ?Item $pendingReward = null;

    public function __construct(
        private Main              $main,
        private ReadOnlyInventory $readOnlyInventory,
        string                    $name,
        Position                  $position
    )
    {
        $this->name = $name;
        $this->position = $position;

        $this->textParticle = new TextParticle(
            "§d$name Crate\n" .
            "§7Right Click §fwith a key §7to open\n" .
            "§7Shift + Right Click §fto preview",
            $position->add(
                0.5,
                1.5,
                0.5
            ),
            $position->getWorld()
        );
    }

    public function spawn(): void
    {
        $world = $this->getWorld();

        if (
            !$world->getBlock($this->position)
                ->hasSameTypeId(
                    VanillaBlocks::CHEST()
                )
        ) {
            $world->setBlock(
                $this->position,
                VanillaBlocks::CHEST()
            );
        }

        $tile = $world->getTile(
            $this->position
        );

        if ($tile instanceof ChestTile) {
            $tile->setName(
                '§5' . $this->name . ' Crate'
            );
        }

        if (!$this->textParticle->isSpawned()) {
            $this->textParticle->spawn();
        }
    }

    public function despawn(): void
    {
        $this->textParticle->deSpawn();

        $this->destroyFloatingItem();
    }

    public function spawnText(
        Player $player
    ): void
    {
        $this->textParticle->spawn(
            $player
        );
    }

    public function getTextParticle(): TextParticle
    {
        return $this->textParticle;
    }

    public function update(
        int $delay = 0
    ): void
    {
        if ($delay > 0) {
            $this->main->getScheduler()
                ->scheduleDelayedTask(
                    new ClosureTask(
                        function (): void {
                            $this->updateContents();
                        }
                    ),
                    $delay
                );

            return;
        }

        $this->updateContents();
    }

    private function updateContents(): void
    {
        if ($this->busy) {
            return;
        }

        $inventory = $this->getInventory();

        if ($inventory === null) {
            return;
        }

        if ($this->hasPreviewViewers()) {
            $this->fillPreview(
                $inventory
            );

            return;
        }

        $inventory->clearAll();
    }

    public function addReward(
        Item   $item,
        float  $weight,
        string $type = Reward::TYPE_COMMON
    ): self
    {
        $this->rewards[] = new Reward(
            $item,
            $weight,
            $type
        );

        return $this;
    }

    public function addRewardByType(
        Item   $item,
        string $type
    ): self
    {
        return $this->addReward(
            $item,
            Reward::getDefaultWeightForType($type),
            $type
        );
    }

    public function addRewardObject(
        Reward $reward
    ): self
    {
        $this->rewards[] = $reward;

        return $this;
    }

    public function getReward(
        int $index
    ): ?Reward
    {
        return $this->rewards[$index] ?? null;
    }

    public function setRewardWeight(
        int   $index,
        float $weight
    ): self
    {
        $reward = $this->getReward($index);

        if ($reward === null) {
            throw new InvalidArgumentException(
                "Reward index $index does not exist."
            );
        }

        $reward->setWeight($weight);

        return $this;
    }

    public function setRewardType(
        int    $index,
        string $type
    ): self
    {
        $reward = $this->getReward($index);

        if ($reward === null) {
            throw new InvalidArgumentException(
                "Reward index $index does not exist."
            );
        }

        $reward->setType($type);

        return $this;
    }

    public function removeReward(
        int $index
    ): self
    {
        if (!isset($this->rewards[$index])) {
            return $this;
        }

        unset($this->rewards[$index]);

        $this->rewards = array_values(
            $this->rewards
        );

        return $this;
    }

    public function removeRewards(): self
    {
        $this->rewards = [];

        $this->update();

        return $this;
    }

    /**
     * @return array<int, Reward>
     */
    public function getRewards(): array
    {
        return $this->rewards;
    }

    public function getTotalWeight(): float
    {
        $total = 0.0;

        foreach ($this->rewards as $reward) {
            $total += $reward->getWeight();
        }

        return $total;
    }

    public function getRewardChance(
        int $index
    ): ?float
    {
        $reward = $this->getReward($index);

        if ($reward === null) {
            return null;
        }

        return $reward->getChancePercent(
            $this->getTotalWeight()
        );
    }

    public function addKey(
        string $keyId
    ): self
    {
        $this->keys[$keyId] = true;

        return $this;
    }

    public function removeKey(
        string $keyId
    ): self
    {
        unset(
            $this->keys[$keyId]
        );

        return $this;
    }

    public function hasKey(
        string $keyId
    ): bool
    {
        return isset(
            $this->keys[$keyId]
        );
    }

    /**
     * @return list<string>
     */
    public function getKeys(): array
    {
        return array_keys(
            $this->keys
        );
    }

    public function isOpening(): bool
    {
        return $this->busy;
    }

    public function isBusy(): bool
    {
        return $this->busy;
    }

    public function hasPreviewViewers(): bool
    {
        return $this->previewViewers !== [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getWorld(): World
    {
        return $this->position->getWorld();
    }

    public function getInventory(): ?Inventory
    {
        $tile = $this->getWorld()->getTile(
            $this->position
        );

        if (!$tile instanceof ChestTile) {
            return null;
        }

        return $tile->getRealInventory();
    }

    public function showPreview(
        Player $player
    ): bool
    {
        if ($this->busy) {
            $player->sendMessage(
                '§eThis crate is currently opening.'
            );

            return false;
        }

        $inventory = $this->getInventory();

        if ($inventory === null) {
            $this->spawn();

            $inventory = $this->getInventory();
        }

        if ($inventory === null) {
            return false;
        }

        $this->fillPreview(
            $inventory
        );

        if (
            !$this->readOnlyInventory->open(
                $player,
                $inventory
            )
        ) {
            return false;
        }

        $this->previewViewers[spl_object_id($player)] = true;

        return true;
    }

    private function fillPreview(
        Inventory $inventory
    ): void
    {
        $inventory->clearAll();

        $slot = 0;

        foreach (
            $this->rewards as $reward
        ) {
            if (
                $slot >=
                $inventory->getSize()
            ) {
                break;
            }

            $inventory->setItem(
                $slot,
                $reward->getItem()
            );

            ++$slot;
        }
    }

    public function open(
        Player $player
    ): bool
    {
        if ($this->busy) {
            $player->sendMessage(
                '§eThis crate is currently opening.'
            );

            return false;
        }

        if ($this->hasPreviewViewers()) {
            $player->sendMessage(
                '§eSomeone is currently viewing this crate.'
            );

            return false;
        }

        if ($this->rewards === []) {
            $player->sendMessage(
                '§cThis crate has no rewards.'
            );

            return false;
        }

        $inventory = $this->getInventory();

        if ($inventory === null) {
            $this->spawn();

            $inventory = $this->getInventory();
        }

        if ($inventory === null) {
            return false;
        }

        $winner = $this->rollReward();

        if ($winner === null) {
            $player->sendMessage(
                '§cNo reward could be selected.'
            );

            return false;
        }

        $this->busy = true;

        $this->openingPlayerId =
            spl_object_id($player);

        $this->pendingReward =
            clone $winner;

        $inventory->clearAll();

        $this->readOnlyInventory->add(
            $inventory
        );

        if (
            !$player->setCurrentWindow(
                $inventory
            )
        ) {
            $this->busy = false;
            $this->openingPlayerId = null;
            $this->pendingReward = null;

            $this->readOnlyInventory->remove(
                $inventory
            );

            return false;
        }

        $this->playAnimation(
            $player,
            $winner
        );

        return true;
    }

    private function rollReward(): ?Item
    {
        if ($this->rewards === []) {
            return null;
        }

        $totalWeight =
            $this->getTotalWeight();

        if ($totalWeight <= 0) {
            return null;
        }

        $random =
            (mt_rand() / mt_getrandmax())
            * $totalWeight;

        $current = 0.0;

        foreach (
            $this->rewards as $reward
        ) {
            $current +=
                $reward->getWeight();

            if ($random < $current) {
                return $reward->getItem();
            }
        }

        return null;
    }

    private function getRandomDisplayItem(): ?Item
    {
        return $this->rollReward();
    }

    private function playAnimation(
        Player $player,
        Item   $winner
    ): void
    {
        $inventory = $this->getInventory();

        if ($inventory === null) {
            $this->finishOpening(
                $player,
                $winner
            );

            return;
        }

        $steps = 36;

        $runStep = function (
            int $step
        ) use (
            &$runStep,
            $player,
            $winner,
            $inventory,
            $steps
        ): void {
            if (!$player->isConnected()) {
                $this->destroyFloatingItem();

                $this->busy = false;
                $this->openingPlayerId = null;
                $this->pendingReward = null;

                $this->readOnlyInventory
                    ->remove($inventory);

                return;
            }

            if (
                $this->openingPlayerId !==
                spl_object_id($player)
            ) {
                return;
            }

            if ($step >= $steps) {
                $inventory->clearAll();

                $inventory->setItem(
                    13,
                    clone $winner
                );

                $this->showFloatingItem(
                    $winner
                );

                $this->playWinEffects();

                $this->main->getScheduler()
                    ->scheduleDelayedTask(
                        new ClosureTask(
                            function () use (
                                $player,
                                $winner
                            ): void {
                                $this->finishOpening(
                                    $player,
                                    $winner
                                );
                            }
                        ),
                        20
                    );

                return;
            }

            $display =
                $this->getRandomDisplayItem();

            if ($display === null) {
                $this->finishOpening(
                    $player,
                    $winner
                );

                return;
            }

            $inventory->clearAll();

            $slots = [
                10,
                11,
                12,
                13,
                14,
                15,
                16
            ];

            foreach (
                $slots as $index => $slot
            ) {
                if ($index === 3) {
                    continue;
                }

                if (mt_rand(0, 100) <= 45) {
                    $random =
                        $this->getRandomDisplayItem();

                    if ($random !== null) {
                        $inventory->setItem(
                            $slot,
                            $random
                        );
                    }
                }
            }

            $inventory->setItem(
                13,
                clone $display
            );

            $this->showFloatingItem(
                $display
            );

            $this->playRollEffects(
                $display
            );

            $this->main->getScheduler()
                ->scheduleDelayedTask(
                    new ClosureTask(
                        function () use (
                            &$runStep,
                            $step
                        ): void {
                            $runStep(
                                $step + 1
                            );
                        }
                    ),
                    $this->getAnimationDelay(
                        $step
                    )
                );
        };

        $runStep(0);
    }

    private function getAnimationDelay(
        int $step
    ): int
    {
        if ($step < 12) {
            return 2;
        }

        if ($step < 22) {
            return 3;
        }

        if ($step < 29) {
            return 4;
        }

        if ($step < 34) {
            return 5;
        }

        return 7;
    }

    private function showFloatingItem(
        Item $item
    ): void
    {
        $this->destroyFloatingItem();

        $position = $this->position->add(
            0.5,
            1.35,
            0.5
        );

        $location = new Location(
            $position->x,
            $position->y,
            $position->z,
            $this->getWorld(),
            0.0,
            0.0
        );

        $entity = new ItemEntity(
            $location,
            clone $item
        );

        $entity->setHasGravity(false);
        $entity->setGravity(0.0);
        $entity->setPickupDelay(
            ItemEntity::NEVER_DESPAWN
        );
        $entity->setDespawnDelay(
            ItemEntity::NEVER_DESPAWN
        );

        $entity->spawnToAll();

        $this->floatingItem = $entity;
    }

    private function destroyFloatingItem(): void
    {
        if ($this->floatingItem === null) {
            return;
        }

        if (
            !$this->floatingItem
                ->isFlaggedForDespawn()
        ) {
            $this->floatingItem
                ->flagForDespawn();
        }

        $this->floatingItem = null;
    }

    private function playRollEffects(
        Item $item
    ): void
    {
        $world = $this->getWorld();

        $center = $this->position->add(
            0.5,
            1.2,
            0.5
        );

        $world->addSound(
            $center,
            new PopSound(
                mt_rand(80, 120) / 100
            )
        );

        $world->addParticle(
            $center,
            new ItemBreakParticle(
                clone $item
            )
        );

        for ($i = 0; $i < 3; ++$i) {
            $angle =
                (M_PI * 2 / 3) * $i;

            $particlePosition =
                $center->add(
                    cos($angle) * 0.45,
                    mt_rand(0, 5) / 10,
                    sin($angle) * 0.45
                );

            $world->addParticle(
                $particlePosition,
                new DustParticle(
                    new Color(
                        170,
                        60,
                        255
                    )
                )
            );
        }
    }

    private function playWinEffects(): void
    {
        $world = $this->getWorld();

        $center = $this->position->add(
            0.5,
            1.35,
            0.5
        );

        $world->addSound(
            $center,
            new XpLevelUpSound(10)
        );

        $world->addParticle(
            $center,
            new ExplodeParticle()
        );

        for ($i = 0; $i < 12; ++$i) {
            $world->addParticle(
                $center->add(
                    mt_rand(-10, 10) / 10,
                    mt_rand(0, 12) / 10,
                    mt_rand(-10, 10) / 10
                ),
                new HappyVillagerParticle()
            );
        }

        for ($i = 0; $i < 8; ++$i) {
            $angle =
                (M_PI * 2 / 8) * $i;

            $world->addParticle(
                $center->add(
                    cos($angle) * 0.7,
                    0.2,
                    sin($angle) * 0.7
                ),
                new DustParticle(
                    new Color(
                        255,
                        190,
                        30
                    )
                )
            );
        }
    }

    private function finishOpening(
        Player $player,
        Item   $winner
    ): void
    {
        $inventory = $this->getInventory();

        $this->destroyFloatingItem();

        $this->busy = false;
        $this->openingPlayerId = null;
        $this->pendingReward = null;

        if ($inventory !== null) {
            $this->readOnlyInventory
                ->remove($inventory);

            $inventory->clearAll();

            if (
                $player->getCurrentWindow() ===
                $inventory
            ) {
                $player->removeCurrentWindow();
            }
        }

        if (!$player->isConnected()) {
            return;
        }

        $leftovers = $player
            ->getInventory()
            ->addItem(
                clone $winner
            );

        foreach ($leftovers as $leftover) {
            $player->dropItem(
                $leftover
            );
        }

        $player->sendMessage(
            '§dCrate Reward: §f' .
            $winner->getName()
        );
    }

    public function handleClose(
        Player $player
    ): void
    {
        $playerId = spl_object_id(
            $player
        );

        if (
            isset(
                $this->previewViewers[$playerId]
            )
        ) {
            unset(
                $this->previewViewers[$playerId]
            );

            if (
                !$this->busy &&
                $this->previewViewers === []
            ) {
                $inventory =
                    $this->getInventory();

                if ($inventory !== null) {
                    $inventory->clearAll();

                    $this->readOnlyInventory
                        ->remove(
                            $inventory
                        );
                }
            }

            return;
        }

        if (
            !$this->busy ||
            $this->openingPlayerId !==
            $playerId
        ) {
            return;
        }

        $inventory = $this->getInventory();

        if ($inventory === null) {
            return;
        }

        $this->main->getScheduler()
            ->scheduleDelayedTask(
                new ClosureTask(
                    function () use (
                        $player,
                        $inventory
                    ): void {
                        if (
                            !$player->isConnected()
                        ) {
                            return;
                        }

                        if (!$this->busy) {
                            return;
                        }

                        if (
                            $player->getCurrentWindow()
                            !== null
                        ) {
                            return;
                        }

                        $player->setCurrentWindow(
                            $inventory
                        );
                    }
                ),
                1
            );
    }

    public function handleQuit(
        Player $player
    ): void
    {
        $playerId = spl_object_id(
            $player
        );

        if (
            $this->openingPlayerId ===
            $playerId
        ) {
            $reward =
                $this->pendingReward;

            $this->destroyFloatingItem();

            $this->busy = false;
            $this->openingPlayerId = null;
            $this->pendingReward = null;

            $inventory =
                $this->getInventory();

            if ($inventory !== null) {
                $this->readOnlyInventory
                    ->remove($inventory);

                $inventory->clearAll();
            }

            if ($reward !== null) {
                $this->getWorld()->dropItem(
                    $this->position->add(
                        0.5,
                        1.0,
                        0.5
                    ),
                    clone $reward
                );
            }

            return;
        }

        if (
            isset(
                $this->previewViewers[$playerId]
            )
        ) {
            unset(
                $this->previewViewers[$playerId]
            );

            if (
                $this->previewViewers === [] &&
                !$this->busy
            ) {
                $inventory =
                    $this->getInventory();

                if ($inventory !== null) {
                    $inventory->clearAll();

                    $this->readOnlyInventory
                        ->remove(
                            $inventory
                        );
                }
            }
        }
    }

    public function save(): void
    {
        $this->main
            ->getCrateManager()
            ->saveCrate(
                $this->name
            );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'world' =>
                $this->getWorld()->getFolderName(),

            'x' => $this->position->x,
            'y' => $this->position->y,
            'z' => $this->position->z,

            'keys' => $this->getKeys(),

            'rewards' => array_map(
                static fn(
                    Reward $reward
                ): array => $reward->toArray(),
                $this->rewards
            )
        ];
    }
}