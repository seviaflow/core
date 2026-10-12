<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\Concerns\HasSelectOptions;
use SeviaFlow\Core\Resources\ResourceContext;

final class SelectField extends Field
{
    use HasSelectOptions;

    protected static string $type = 'select';

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            ...($this->hasDynamicOptions() ? [] : ['enum' => array_values(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->getOptions())))]),
            'nullable' => true,
        ];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [Rule::in($this->getSelectableOptionKeys(new FormState($fieldValues, $context instanceof ResourceContext ? $context : ResourceContext::Create, $record instanceof Model ? $record : null)))];
    }
}
