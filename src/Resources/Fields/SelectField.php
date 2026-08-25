<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Validation\Rule;
use SeviaFlow\Core\Resources\ResourceContext;

final class SelectField extends Field
{
    /**
     * @var array<array-key, string>
     */
    private array $options = [];

    /**
     * @param  array<array-key, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [Rule::in(array_keys($this->options))];
    }
}
