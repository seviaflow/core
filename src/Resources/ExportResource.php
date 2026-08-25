<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;

final class ExportResource
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_FORMATS = ['csv'];

    private bool $enabled = false;

    /**
     * @var array<int, ExportField>
     */
    private array $allowedFields = [];

    /**
     * @var array<int, string>
     */
    private array $formats = ['csv'];

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
     * @param  array<int, ExportField>  $fields
     */
    public function allowedFields(array $fields): static
    {
        $this->allowedFields = $this->normalizeFields($fields);

        return $this;
    }

    /**
     * @return array<int, ExportField>
     */
    public function getAllowedFields(): array
    {
        return $this->allowedFields;
    }

    /**
     * @param  array<int, string>  $formats
     */
    public function formats(array $formats): static
    {
        $normalized = [];

        foreach ($formats as $index => $format) {
            if (! is_string($format)) {
                throw new InvalidArgumentException(sprintf(
                    'ExportResource::formats value at index %d must be a non-empty string, [%s] given.',
                    $index,
                    get_debug_type($format)
                ));
            }

            $value = strtolower(trim($format));
            if ($value === '') {
                throw new InvalidArgumentException(sprintf(
                    'ExportResource::formats value at index %d must be a non-empty string.',
                    $index
                ));
            }

            if (! in_array($value, self::SUPPORTED_FORMATS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'ExportResource::formats value [%s] is not supported. Supported formats: %s.',
                    $value,
                    implode(', ', self::SUPPORTED_FORMATS)
                ));
            }

            $normalized[] = $value;
        }

        $this->formats = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    /**
     * @param  array<int, ExportField>  $fields
     * @return array<int, ExportField>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        $seen = [];

        foreach ($fields as $index => $field) {
            if (! $field instanceof ExportField) {
                throw new InvalidArgumentException(sprintf(
                    'ExportResource::allowedFields value at index %d must be an instance of %s, [%s] given.',
                    $index,
                    ExportField::class,
                    get_debug_type($field)
                ));
            }

            if (isset($seen[$field->name()])) {
                continue;
            }

            $seen[$field->name()] = true;
            $normalized[] = $field;
        }

        return $normalized;
    }
}
