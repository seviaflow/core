<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\ToggleField;

final class FormStateInitializer
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initialize(array $fields, ?Model $record): array
    {
        $fieldValues = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $value = $record?->getAttribute($name);

            if ($field instanceof PasswordField) {
                $fieldValues[$name] = '';

                continue;
            }

            if ($field instanceof ToggleField) {
                $fieldValues[$name] = (bool) $value;

                continue;
            }

            if ($field instanceof MultiSelectField) {
                if (is_array($value)) {
                    $fieldValues[$name] = $value;

                    continue;
                }

                $fieldValues[$name] = [];

                continue;
            }

            if ($field instanceof FileField) {
                $fieldValues[$name] = null;

                continue;
            }

            if ($value === null) {
                $fieldValues[$name] = '';

                continue;
            }

            $fieldValues[$name] = (string) $value;
        }

        return $fieldValues;
    }
}
