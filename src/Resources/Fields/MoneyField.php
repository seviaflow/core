<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

final class MoneyField extends NumberField
{
    private ?string $currency = null;

    public function currency(string $currency): static
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function getCurrency(): string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        $configured = config('seviaflow.forms.default_currency');

        return is_string($configured) && $configured !== ''
            ? strtoupper($configured)
            : 'USD';
    }
}
