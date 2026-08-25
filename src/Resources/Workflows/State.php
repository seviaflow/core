<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Workflows;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class State
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_COLORS = [
        'gray',
        'red',
        'yellow',
        'green',
        'blue',
        'indigo',
        'purple',
        'pink',
    ];

    private ?string $label = null;

    private ?string $color = null;

    private bool $final = false;

    private function __construct(
        private readonly string $name
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Workflow state name cannot be empty.');
        }
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(?string $label = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return Str::of($this->name)
            ->replace(['_', '-'], ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    public function color(?string $color = null): static
    {
        if ($color === null) {
            $this->color = null;

            return $this;
        }

        $normalized = strtolower(trim($color));

        if ($normalized === '') {
            throw new InvalidArgumentException('Workflow state color cannot be empty.');
        }

        if (! in_array($normalized, self::ALLOWED_COLORS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Workflow state color [%s] is not allowed.',
                $color
            ));
        }

        $this->color = $normalized;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function final(bool $condition = true): static
    {
        $this->final = $condition;

        return $this;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }
}
