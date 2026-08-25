<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class SlugField extends Field
{
    private ?string $sourceField = null;

    public function from(string $field): static
    {
        $this->sourceField = $field;

        return $this;
    }

    public function getSourceField(): ?string
    {
        return $this->sourceField;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }
}
