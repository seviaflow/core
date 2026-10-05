<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class FormState
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        private readonly array $values,
        private readonly ResourceContext $context,
        private readonly ?Model $record = null,
    ) {}

    public function value(string $field, mixed $default = null): mixed
    {
        return array_key_exists($field, $this->values) ? $this->values[$field] : $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function context(): ResourceContext
    {
        return $this->context;
    }

    public function record(): ?Model
    {
        return $this->record;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function withValues(array $values): self
    {
        return new self($values, $this->context, $this->record);
    }
}
