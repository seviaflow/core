<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class ExportField
{
    private ?string $label = null;

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        $normalized = trim($name);

        if ($normalized === '') {
            throw new InvalidArgumentException('ExportField name must be a non-empty string.');
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $normalized)) {
            throw new InvalidArgumentException(sprintf(
                'ExportField name [%s] is invalid. Use letters, numbers, underscores, and dots for nested fields.',
                $normalized
            ));
        }

        return new self($normalized);
    }

    public function label(string $label): self
    {
        $normalized = trim($label);

        if ($normalized === '') {
            throw new InvalidArgumentException('ExportField label must be a non-empty string.');
        }

        $field = clone $this;
        $field->label = $normalized;

        return $field;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        if (is_string($this->label) && $this->label !== '') {
            return $this->label;
        }

        return Str::headline(str_replace('.', ' ', $this->name));
    }
}
