<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\PasswordField;

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

            return $value->store($field->getDirectory(), [
                'disk' => $field->getDisk(),
                'visibility' => $field->getVisibility(),
            ]);
        }

        return $field->normalizeValue($value, $model);
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
        return $field->prepareForValidation($value);
    }

    public function prepareForImport(Field $field, mixed $value): mixed
    {
        return $field->prepareForImport($value);
    }

    private function hostHashesAttribute(Model $model, string $name): bool
    {
        return $model->hasSetMutator($name)
            || $model->hasAttributeSetMutator($name)
            || $model->hasCast($name, 'hashed');
    }
}
