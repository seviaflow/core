<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;

final class ApiResource
{
    private bool $enabled = false;

    /**
     * @var array<int, string>
     */
    private array $readableFields = [];

    /**
     * @var array<int, string>
     */
    private array $creatableFields = [];

    /**
     * @var array<int, string>
     */
    private array $updatableFields = [];

    /**
     * @var array<int, string>
     */
    private array $requiredOnCreateFields = [];

    /**
     * @var array<int, string>
     */
    private array $requiredOnUpdateFields = [];

    private bool $deletable = false;

    private bool $flowEnabled = false;

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
     * @param  array<int, string>  $fields
     */
    public function readable(array $fields): static
    {
        $this->readableFields = $this->normalizeFields($fields, 'readable');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getReadableFields(): array
    {
        return $this->readableFields;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function creatable(array $fields): static
    {
        $this->creatableFields = $this->normalizeFields($fields, 'creatable');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCreatableFields(): array
    {
        return $this->creatableFields;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function updatable(array $fields): static
    {
        $this->updatableFields = $this->normalizeFields($fields, 'updatable');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getUpdatableFields(): array
    {
        return $this->updatableFields;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function requiredOnCreate(array $fields): static
    {
        $this->requiredOnCreateFields = $this->normalizeFields($fields, 'requiredOnCreate');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredOnCreateFields(): array
    {
        return $this->requiredOnCreateFields;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function requiredOnUpdate(array $fields): static
    {
        $this->requiredOnUpdateFields = $this->normalizeFields($fields, 'requiredOnUpdate');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredOnUpdateFields(): array
    {
        return $this->requiredOnUpdateFields;
    }

    public function deletable(bool $condition = true): static
    {
        if ($condition) {
            $this->deletable = true;
        }

        return $this;
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }

    public function flow(bool $condition = true): static
    {
        if ($condition) {
            $this->flowEnabled = true;
        }

        return $this;
    }

    public function isFlowEnabled(): bool
    {
        return $this->flowEnabled;
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
                    'ApiResource::%s field at index %d must be a non-empty string, [%s] given.',
                    $method,
                    $index,
                    get_debug_type($field)
                ));
            }

            $value = trim($field);

            if ($value === '') {
                throw new InvalidArgumentException(sprintf(
                    'ApiResource::%s field at index %d must be a non-empty string.',
                    $method,
                    $index
                ));
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value)) {
                throw new InvalidArgumentException(sprintf(
                    'ApiResource::%s field [%s] is invalid. Use letters, numbers, underscores, and dots for nested fields.',
                    $method,
                    $value
                ));
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}
