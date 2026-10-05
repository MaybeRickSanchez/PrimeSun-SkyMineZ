<?php

declare(strict_types=1);

namespace AM\SkyMineZ\form;

use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * A `type: form` window: a title, one line of content and a column of buttons.
 *
 * The response is the zero-based index of the pressed button, or null when the
 * player closed the window. When a label was supplied for a button the closure
 * receives the label instead of the index, which lets callers use readable keys
 * that stay correct even after buttons are inserted or removed.
 */
class SimpleForm extends Form
{
    public const IMAGE_TYPE_PATH = 0;
    public const IMAGE_TYPE_URL = 1;

    /**
     * @var list<array{text: string, image?: array{type: string, data: string}}>
     */
    private array $buttons = [];

    /**
     * @var list<string|int>
     */
    private array $labelMap = [];

    private string $content = '';

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function __construct(
        ?callable $callable = null,
        ?callable $onCompletion = null
    ) {
        parent::__construct($callable, $onCompletion);

        $this->data['type'] = 'form';
        $this->data['title'] = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        return [
            'content' => $this->content,
            'buttons' => $this->buttons
        ];
    }

    public function processData(&$data): void
    {
        if ($data === null) {
            return;
        }

        if (!is_int($data)) {
            throw new FormValidationException(
                'Expected an integer response, got ' . gettype($data)
            );
        }

        $count = count($this->buttons);

        if (
            $data < 0
            || $data >= $count
        ) {
            throw new FormValidationException(
                "Button $data does not exist"
            );
        }

        $data = $this->labelMap[$data] ?? $data;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(
        string $content
    ): static {
        $this->content = $content;

        return $this;
    }

    /**
     * Appends a button.
     *
     * @param string          $text      button caption
     * @param int             $imageType {@link self::IMAGE_TYPE_PATH} or {@link self::IMAGE_TYPE_URL}, -1 for none
     * @param string          $imagePath file path or URL shown next to the caption
     * @param string|int|null $label     value handed to the closure instead of the raw index
     */
    public function addButton(
        string $text,
        int $imageType = -1,
        string $imagePath = '',
        string|int|null $label = null
    ): static {
        $this->buttons[] = $this->buildButton(
            $text,
            $imageType,
            $imagePath
        );

        $this->labelMap[] = $label ?? count(
            $this->labelMap
        );

        return $this;
    }

    /**
     * Appends a button whose label is its own caption, so the closure can switch
     * on readable text.
     */
    public function addTextButton(
        string $text
    ): static {
        return $this->addButton(
            $text,
            -1,
            '',
            $text
        );
    }

    /**
     * @param list<string> $texts
     */
    public function addButtons(
        array $texts
    ): static {
        foreach (
            $texts as $text
        ) {
            $this->addButton($text);
        }

        return $this;
    }

    /**
     * Inserts a button at a specific position, keeping the labels aligned.
     */
    public function insertButton(
        int $index,
        string $text,
        int $imageType = -1,
        string $imagePath = '',
        string|int|null $label = null
    ): static {
        if (
            $index < 0
            || $index > count($this->buttons)
        ) {
            return $this;
        }

        array_splice(
            $this->buttons,
            $index,
            0,
            [
                $this->buildButton(
                    $text,
                    $imageType,
                    $imagePath
                )
            ]
        );

        array_splice(
            $this->labelMap,
            $index,
            0,
            [
                $label ?? $index
            ]
        );

        return $this;
    }

    /**
     * @return list<array{text: string, image?: array{type: string, data: string}}>
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    public function countButtons(): int
    {
        return count($this->buttons);
    }

    public function resetButtons(): static
    {
        $this->buttons = [];
        $this->labelMap = [];

        return $this;
    }

    /**
     * @return array{text: string, image?: array{type: string, data: string}}
     */
    private function buildButton(
        string $text,
        int $imageType,
        string $imagePath
    ): array {
        $button = ['text' => $text];

        if ($imageType !== -1) {
            $button['image'] = [
                'type' => $imageType === self::IMAGE_TYPE_PATH
                    ? 'path'
                    : 'url',
                'data' => $imagePath
            ];
        }

        return $button;
    }
}