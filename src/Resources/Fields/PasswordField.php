<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class PasswordField extends Field
{
    private bool $dehydrateOnlyWhenFilled = true;

    public function dehydrateOnlyWhenFilled(bool $enabled = true): static
    {
        $this->dehydrateOnlyWhenFilled = $enabled;

        return $this;
    }

    public function shouldDehydrateOnlyWhenFilled(): bool
    {
        return $this->dehydrateOnlyWhenFilled;
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
