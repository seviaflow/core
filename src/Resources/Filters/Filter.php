<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

abstract class Filter
{
    private ?string $label = null;

    private ?string $placeholder = null;

    private mixed $default = null;

    final protected function __construct(
        private readonly string $name
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
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

    public function placeholder(?string $placeholder = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isActive(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    abstract public function apply(Builder $query, mixed $value): Builder;
}
