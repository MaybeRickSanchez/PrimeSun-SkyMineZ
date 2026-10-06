<?php

declare(strict_types=1);

namespace AM\SkyMineZ\form;

use pocketmine\form\Form as IForm;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * Base class for every form shipped with SkyMineZ.
 *
 * The shape of this class follows jojoe77777's FormAPI: build the payload with
 * the fluent setters and hand a single closure to the constructor, which is
 * invoked with the (already validated and relabelled) response.
 *
 * Two additions over the original are required by PocketMine-MP 5.44 and are
 * therefore implemented here:
 *
 *  - {@link IForm::getOnCompletion()} / retries / kick message / blocking.
 *  - Response processing is separated from the user callback, so a form can
 *    validate and normalise the raw response before the closure sees it.
 *
 * Example:
 *
 *     $form = new SimpleForm(function(Player $player, ?int $data): void {
 *         if ($data !== null) {
 *             $player->sendMessage("You picked button " . $data);
 *         }
 *     });
 *     $form->setTitle("Menu");
 *     $form->addButton("Spawn");
 *     $form->sendToPlayer($player);
 */
abstract class Form implements IForm
{
    /**
     * Serialised form payload.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @var callable(Player, mixed): void|null
     */
    private $callable;

    /**
     * @var callable(Player): void|null
     */
    private $onCompletion;

    private ?int $maxRetries = null;

    private ?string $kickMessage = null;

    private bool $blocking = true;

    /**
     * @param callable(Player, mixed): void|null $callable
     * @param callable(Player): void|null $onCompletion
     */
    public function __construct(
        ?callable $callable = null,
        ?callable $onCompletion = null
    ) {
        $this->callable = $callable;
        $this->onCompletion = $onCompletion;
    }

    /**
     * Sends the form to $player.
     *
     * Returns false when the player is no longer connected, so callers do not
     * have to guard every call site.
     */
    public function sendToPlayer(Player $player): bool
    {
        if (!$player->isConnected()) {
            return false;
        }

        $player->sendForm($this);

        return true;
    }

    public function handleResponse(Player $player, mixed $data): void
    {
        $this->processData($data);

        $callable = $this->callable;

        if ($callable !== null) {
            $callable($player, $data);
        }
    }

    /**
     * Hook for subclasses to validate and rewrite the raw response.
     *
     * $data is passed by reference on purpose: implementations replace it with
     * the normalised value that the user callback should receive.
     *
     * @param mixed $data
     *
     * @throws FormValidationException
     */
    public function processData(&$data): void
    {
    }

    /**
     * @return array<string, mixed>
     */
public function jsonSerialize(): array
    {
        return array_merge(
            $this->data,
            $this->buildPayload()
        );
    }

    /**
     * Hook for subclasses to expose their own mutable state to the client.
     *
     * Keeping the state in typed properties instead of the raw payload array is
     * what makes the getters type safe, and this hook is how it gets serialised.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        return [];
    }

    public function getOnCompletion(): ?callable
    {
        return $this->onCompletion;
    }

    public function getMaxRetries(): ?int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(?int $maxRetries): static
    {
        $this->maxRetries = $maxRetries !== null && $maxRetries < 0
            ? null
            : $maxRetries;

        return $this;
    }

    public function getKickMessage(): ?string
    {
        return $this->kickMessage;
    }

    public function setKickMessage(?string $kickMessage): static
    {
        $this->kickMessage = $kickMessage;

        return $this;
    }

    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    public function setBlocking(bool $blocking): static
    {
        $this->blocking = $blocking;

        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->data['title'] = $title;

        return $this;
    }

}