<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class ImageField extends FileField
{
    public static function make(string $name): static
    {
        $field = parent::make($name);
        $field->acceptedFileTypes([
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);

        return $field;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $rules = parent::defaultValidationRules($context, $record, $modelClass, $fieldValues);
        $rules[] = 'image';

        return $rules;
    }
}
