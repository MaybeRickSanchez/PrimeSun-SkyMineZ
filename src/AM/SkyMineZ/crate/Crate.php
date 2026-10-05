<?php

declare(strict_types=1);

namespace AM\SkyMineZ\crate;

use AM\SkyMineZ\Main;
use AM\SkyMineZ\event\CrateOpenEvent;
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

    private ?Reward $pendingReward = null;

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

    /**
     * The chest inventory, rebuilding the chest block first when it went
     * missing. Both preview and opening need this, so it lives here instead of
     * being copy-pasted into both.
     */
    private function getOrSpawnInventory(): ?Inventory
    {
        $inventory = $this->getInventory();

        if ($inventory === null) {
            $this->spawn();

            $inventory = $this->getInventory();
        }

        return $inventory;
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

        $inventory = $this->getOrSpawnInventory();

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

    /**
     * Starts an opening.
     *
     * The key is *not* consumed here: the caller decides that, after this method
     * reported success. Consuming it here would hand out a reward even when the
     * animation could not be started.
     */
    public function open(
        Player $player
    ): bool {
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

        $inventory = $this->getOrSpawnInventory();

        if ($inventory === null) {
            $player->sendMessage(
                '§cThis crate could not be loaded.'
            );

            return false;
        }

        $winner = $this->rollReward();

        if ($winner === null) {
            $player->sendMessage(
                '§cNo reward could be selected.'
            );

            return false;
        }

        if (CrateOpenEvent::hasHandlers()) {
            $event = new CrateOpenEvent(
                $this,
                $player->getName(),
                $winner
            );

            $event->call();

            if ($event->isCancelled()) {
                return false;
            }

            $winner = $event->getReward();
        }

        $this->busy = true;

        $this->openingPlayerId =
            spl_object_id($player);

        $this->pendingReward = $winner;

        $inventory->clearAll();

        $this->readOnlyInventory->add(
            $inventory
        );

        if (!$player->setCurrentWindow($inventory)) {
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

    /**
     * Rolls one of the configured rewards, weighted by {@link Reward::getWeight()}.
     *
     * Returns a fresh copy, so the caller can never mutate the stored reward.
     */
    private function rollReward(): ?Reward
    {
        if ($this->rewards === []) {
            return null;
        }

        $totalWeight = $this->getTotalWeight();

        if ($totalWeight <= 0) {
            return null;
        }

        $random = (
            mt_rand() / mt_getrandmax()
        ) * $totalWeight;

        $current = 0.0;

        foreach (
            $this->rewards as $reward
        ) {
            $current += $reward->getWeight();

            if ($random < $current) {
                return $reward;
            }
        }

        return null;
    }

    /**
     * A throwaway item used as a decoration while the crate spins.
     */
    private function getRandomDisplayItem(): ?Item
    {
        $reward = $this->rollReward();

        return $reward?->getItem();
    }

    /**
     * Plays the spin, then hands the winner over.
     *
     * Every frame is one delayed task rather than a loop, so a crate opening
     * costs one task per frame for one crate and never blocks a tick. The frame
     * delay grows towards the end so the spin looks like it is slowing down.
     */
    private function playAnimation(
        Player $player,
        Reward $winner
    ): void {
        $inventory = $this->getInventory();

        if ($inventory === null) {
            $this->finishOpening(
                $player,
                $winner
            );

            return;
        }

        $steps = $this->main
            ->getConfigManager()
            ->getInt('crates.animation-steps', 36);

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
                    $winner->getItem()
                );

                $this->showFloatingItem(
                    $winner->getItem()
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
                        $this->main
                            ->getConfigManager()
                            ->getInt('crates.reveal-delay', 20)
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

    /**
     * Ticks to wait before the next spin frame.
     *
     * The table comes from config (`crates.animation-delays`), keyed by the first
     * frame of each range. A frame past the last key reuses that key's delay, so
     * a server with more steps than the default table still looks correct.
     */
private function getAnimationDelay(
        int $step
    ): int {
        $table = $this->main
            ->getConfigManager()
            ->get('crates.animation-delays');

        if (!is_array($table) || $table === []) {
            return 3;
        }

        $delay = null;
        $bestFrom = -1;

        foreach ($table as $from => $ticks) {
            if (!is_numeric($from) || !is_numeric($ticks)) {
                continue;
            }

            $from = (int) $from;

            if ($from > $step || $from <= $bestFrom) {
                continue;
            }

            $bestFrom = $from;
            $delay = (int) $ticks;
        }

        return max(
            1,
            $delay ?? 3
        );
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

    /**
     * Closes the crate window and gives the item to the player. Leftovers are
     * dropped at their feet rather than deleted, so a full inventory never eats
     * a paid reward.
     */
    private function finishOpening(
        Player $player,
        Reward $winner
    ): void {
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

        $item = $winner->getItem();

        $leftovers = $player
            ->getInventory()
            ->addItem($item);

        foreach (
            $leftovers as $leftover
        ) {
            $player->dropItem($leftover);
        }

        $player->sendMessage(
            '§dCrate Reward: §f' . $item->getName()
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
                    $reward->getItem()
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
            ->save(
                $this->name
            );
    }

    /**
     * @return array{
     *     world: string,
     *     x: float,
     *     y: float,
     *     z: float,
     *     keys: list<string>,
     *     rewards: list<array{item: string, weight: float, type: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'world' =>
                $this->getWorld()->getFolderName(),

            'x' => (float) $this->position->x,
            'y' => (float) $this->position->y,
            'z' => (float) $this->position->z,

            'keys' => $this->getKeys(),

            'rewards' => array_values(
                array_map(
                    static fn(
                        Reward $reward
                    ): array => $reward->toArray(),
                    $this->rewards
                )
            )
        ];
    }
}