<?php

declare(strict_types=1);

namespace AM\SkyMineZ\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * A `type: modal` window: two buttons side by side, yes/no style.
 *
 * The response is a bool: true for button1, false for button2, and null when
 * the player dismissed the window.
 */
class ModalForm extends Form
{
    private string $content = '';

    private string $button1 = '';

    private string $button2 = '';

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function __construct(
        ?callable $callable = null,
        ?callable $onCompletion = null
    ) {
        parent::__construct($callable, $onCompletion);

        $this->data['type'] = 'modal';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        return [
            'content' => $this->content,
            'button1' => $this->button1,
            'button2' => $this->button2
        ];
    }

    public function processData(&$data): void
    {
        if ($data === null) {
            return;
        }

        if (!is_bool($data)) {
            throw new FormValidationException(
                'Expected a boolean response, got ' . gettype($data)
            );
        }
    }

    public function setContent(
        string $content
    ): static {
        $this->content = $content;

        return $this;
    }

    public function setButton1(
        string $text
    ): static {
        $this->button1 = $text;

        return $this;
    }

    public function setButton2(
        string $text
    ): static {
        $this->button2 = $text;

        return $this;
    }

    /**
     * Builds the modal, sends it, and runs $onConfirm when button1 is pressed.
     *
     * @param callable(Player): void|null $onConfirm
     * @param callable(Player): void|null $onCompletion
     *
     * @return bool false when the player was no longer connected
     */
    public function ask(
        Player $player,
        string $title,
        string $content,
        string $confirm = 'Yes',
        string $cancel = 'No',
        ?callable $onConfirm = null,
        ?callable $onCompletion = null
    ): bool {
        $form = new self(
            static function(
                Player $who,
                mixed $data
            ) use ($onConfirm): void {
                if (
                    $data === true
                    && $onConfirm !== null
                ) {
                    $onConfirm($who);
                }
            },
            $onCompletion
        );

        $form
            ->setTitle($title)
            ->setContent($content)
            ->setButton1($confirm)
            ->setButton2($cancel);

        return $form->sendToPlayer($player);
    }
}