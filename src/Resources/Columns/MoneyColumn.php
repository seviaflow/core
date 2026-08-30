<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns;

use InvalidArgumentException;

final class MoneyColumn extends Column
{
    private ?string $currency = null;

    private int $decimals = 2;

    public function currency(string $currency): static
    {
        $currency = strtoupper(trim($currency));

        if ($currency === '') {
            throw new InvalidArgumentException('MoneyColumn currency cannot be empty.');
        }

        $this->currency = $currency;

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

    public function decimals(int $decimals): static
    {
        if ($decimals < 0 || $decimals > 8) {
            throw new InvalidArgumentException('MoneyColumn decimals must be between 0 and 8.');
        }

        $this->decimals = $decimals;

        return $this;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }
}
