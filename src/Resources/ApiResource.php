<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;

final class ApiResource
{
    private bool $enabled = false;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $fieldSchemas = [];

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

    public function isReadable(): bool
    {
        return $this->enabled && $this->readableFields !== [];
    }

    /**
     * @param  array<int, mixed>  $fields
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

    /**
     * Declare transport schemas for API fields that are not represented by a
     * Core Form field, or override a field schema supplied by a Form.
     *
     * @param  array<string, array<string, mixed>>  $schemas
     */
    public function schema(array $schemas): static
    {
        foreach ($schemas as $field => $schema) {
            if (! is_string($field) || trim($field) === '') {
                throw new InvalidArgumentException('ApiResource::schema keys must be non-empty field names.');
            }

            $normalizedField = trim($field);
            $this->normalizeFields([$normalizedField], 'schema');

            if (! is_array($schema)) {
                throw new InvalidArgumentException(sprintf(
                    'ApiResource::schema for field [%s] must be an array.',
                    $normalizedField
                ));
            }

            $this->fieldSchemas[$normalizedField] = array_replace_recursive(
                $this->fieldSchemas[$normalizedField] ?? [],
                $schema
            );
        }

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSchema(): array
    {
        return $this->fieldSchemas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function schemaFor(string $field): ?array
    {
        return $this->fieldSchemas[$field] ?? null;
    }

    public function allowsOperation(string $operation): bool
    {
        return match ($operation) {
            'index', 'show', 'file' => $this->isReadable(),
            'store' => $this->isCreatable(),
            'update' => $this->isUpdatable(),
            'destroy' => $this->allowsDelete(),
            'flow' => $this->isReadable() && $this->allowsFlow(),
            'execute-flow' => $this->allowsFlow(),
            default => false,
        };
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

    public function isCreatable(): bool
    {
        return $this->enabled && $this->creatableFields !== [];
    }

    public function isUpdatable(): bool
    {
        return $this->enabled && $this->updatableFields !== [];
    }

    public function allowsDelete(): bool
    {
        return $this->enabled && $this->deletable;
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

    public function allowsFlow(): bool
    {
        return $this->enabled && $this->flowEnabled;
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
