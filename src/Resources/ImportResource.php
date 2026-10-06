<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;

final class ImportResource
{
    private bool $enabled = false;

    /**
     * @var array<int, string>
     */
    private array $allowedFields = [];

    /**
     * @var array<int, string>
     */
    private array $requiredFields = [];

    private bool $useFormFieldRules = false;

    public function enabled(bool $condition = true): static
    {
        if ($condition) {
            $this->enabled = true;
        }

        return $this;
    }

    public function disabled(bool $condition = true): static
    {
        if ($condition) {
            $this->enabled = false;
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    public function allowedFields(array $fields): static
    {
        $this->allowedFields = $this->normalizeFields($fields, 'allowedFields');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedFields(): array
    {
        return $this->allowedFields;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function requiredFields(array $fields): static
    {
        $this->requiredFields = $this->normalizeFields($fields, 'requiredFields');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredFields(): array
    {
        return $this->requiredFields;
    }

    public function useFormFieldRules(bool $condition = true): static
    {
        if ($condition) {
            $this->useFormFieldRules = true;
        }

        return $this;
    }

    public function usesFormFieldRules(): bool
    {
        return $this->useFormFieldRules;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function normalizeFields(array $fields, string $method): array
    {
        $normalized = [];

        foreach ($fields as $index => $field) {
            if (! is_string($field)) {
                throw new InvalidArgumentException(sprintf(
                    'ImportResource::%s field at index %d must be a non-empty string, [%s] given.',
                    $method,
                    $index,
                    get_debug_type($field)
                ));
            }

            $value = trim($field);

            if ($value === '') {
                throw new InvalidArgumentException(sprintf(
                    'ImportResource::%s field at index %d must be a non-empty string.',
                    $method,
                    $index
                ));
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value)) {
                throw new InvalidArgumentException(sprintf(
                    'ImportResource::%s field [%s] is invalid. Use letters, numbers, underscores, and dots for nested fields.',
                    $method,
                    $value
                ));
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}
