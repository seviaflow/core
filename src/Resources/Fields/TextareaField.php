<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class TextareaField extends Field
{
    protected static string $type = 'textarea';

    private int $rows = 3;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['string'];
    }
}
