<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\ToggleField;

final class FieldValueNormalizer
{
    public function normalize(Field $field, mixed $value, ?Model $model = null): mixed
    {
        if ($field instanceof PasswordField) {
            if ($value === null || $value === '') {
                return null;
            }

            $plainText = trim((string) $value);
            if ($model !== null && $this->hostHashesAttribute($model, $field->name())) {
                return $plainText;
            }

            return Hash::make($plainText);
        }

        if ($field instanceof FileField) {
            if (! ($value instanceof UploadedFile)) {
                return null;
            }

            return $value->store($field->getDirectory(), $field->getDisk());
        }

        if ($field instanceof ToggleField) {
            return $this->normalizeBoolean($value);
        }

        if ($field instanceof MultiSelectField) {
            return $this->normalizeMultiSelect($value);
        }

        if ($field instanceof MoneyField) {
            return $value === null || $value === '' ? null : trim((string) $value);
        }

        if ($field instanceof NumberField) {
            return $this->normalizeNumber($value);
        }

        return $this->normalizeUnknown($value);
    }

    public function normalizeUnknown(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        return $value;
    }

    public function prepareForValidation(Field $field, mixed $value): mixed
    {
        if ($field instanceof ToggleField) {
            $boolean = $this->parseBoolean($value);

            return $boolean === null ? $value : $boolean;
        }

        if ($field instanceof MultiSelectField && is_array($value)) {
            return $this->normalizeMultiSelect($value);
        }

        return is_string($value) ? trim($value) : $value;
    }

    public function prepareForImport(Field $field, mixed $value): mixed
    {
        if ($field instanceof MultiSelectField && is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizeMultiSelect($decoded);
            }

            return $this->normalizeMultiSelect(explode(',', $value));
        }

        return $this->prepareForValidation($field, $value);
    }

    private function normalizeNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return strpbrk($normalized, '.eE') !== false
            ? (float) $normalized
            : (int) $normalized;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeMultiSelect(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = is_string($item) ? trim($item) : $item;
            if ($item === '') {
                continue;
            }

            if (! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $parsed = $this->parseBoolean($value);

        return $parsed ?? (bool) $value;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }

    private function hostHashesAttribute(Model $model, string $name): bool
    {
        return $model->hasSetMutator($name)
            || $model->hasAttributeSetMutator($name)
            || $model->hasCast($name, 'hashed');
    }
}
