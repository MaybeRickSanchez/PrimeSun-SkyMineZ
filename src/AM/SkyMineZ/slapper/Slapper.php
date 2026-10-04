<?php

declare(strict_types=1);

namespace AM\SkyMineZ\slapper;

use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\player\Player;

final class Slapper
{
    /**
     * @var list<string>
     */
    private array $commands = [];

    /**
     * @var list<string>
     */
    private array $messages = [];

    private ?Human $entity = null;

    public function __construct(
        private string $name,
        private Location $location,
        private Skin $skin
    ) {
    }

    public function spawn(): void
    {
        $this->despawn();

        $this->entity = new Human(
            $this->location,
            $this->skin
        );

        $this->entity->setNameTag(
            $this->name
        );

        $this->entity->setNameTagVisible(
            true
        );

        $this->entity->setNameTagAlwaysVisible(
            true
        );

        $this->entity->setHasGravity(
            false
        );

        $this->entity->setCanSaveWithChunk(
            false
        );

        $this->entity->spawnToAll();
    }

    public function despawn(): void
    {
        if ($this->entity === null) {
            return;
        }

        if (
            !$this->entity
                ->isFlaggedForDespawn()
        ) {
            $this->entity->flagForDespawn();
        }

        $this->entity = null;
    }

    public function getEntity(): ?Human
    {
        return $this->entity;
    }

    public function getEntityId(): ?int
    {
        return $this->entity?->getId();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(
        string $name
    ): self {
        $this->name = $name;

        if ($this->entity !== null) {
            $this->entity->setNameTag(
                $name
            );
        }

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getSkin(): Skin
    {
        return $this->skin;
    }

    public function setSkin(
        Skin $skin
    ): self {
        $this->skin = $skin;

        if ($this->entity !== null) {
            $this->entity->setSkin(
                $skin
            );

            $this->entity->sendSkin();
        }

        return $this;
    }

    public function addCommand(
        string $command
    ): self {
        $this->commands[] = $command;

        return $this;
    }

    public function addMessage(
        string $message
    ): self {
        $this->messages[] = $message;

        return $this;
    }

    public function clearCommands(): self
    {
        $this->commands = [];

        return $this;
    }

    public function clearMessages(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function execute(
        Player $player
    ): void {
        foreach ($this->messages as $message) {
            $player->sendMessage(
                $this->replacePlaceholders(
                    $message,
                    $player
                )
            );
        }

        foreach ($this->commands as $command) {
            $command =
                $this->replacePlaceholders(
                    $command,
                    $player
                );

            $this->location
                ->getWorld()
                ->getServer()
                ->dispatchCommand(
                    $player,
                    ltrim($command, '/')
                );
        }
    }

    private function replacePlaceholders(
        string $text,
        Player $player
    ): string {
        return str_replace(
            [
                '{player}',
                '{name}'
            ],
            [
                $player->getName(),
                $player->getName()
            ],
            $text
        );
    }

    public function toArray(): array
    {
        return [
            'world' =>
                $this->location
                    ->getWorld()
                    ->getFolderName(),

            'x' => $this->location->x,
            'y' => $this->location->y,
            'z' => $this->location->z,

            'yaw' => $this->location->yaw,
            'pitch' => $this->location->pitch,

            'commands' => $this->commands,
            'messages' => $this->messages,

            'skin' => [
                'id' => $this->skin->getSkinId(),
                'data' => base64_encode(
                    $this->skin->getSkinData()
                ),
                'cape' => base64_encode(
                    $this->skin->getCapeData()
                ),
                'geometryName' =>
                    $this->skin->getGeometryName(),
                'geometryData' => base64_encode(
                    $this->skin->getGeometryData()
                )
            ]
        ];
    }
}