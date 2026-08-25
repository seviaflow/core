<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

final class MoneyFilter extends NumberRangeFilter
{
    private ?string $currency = null;

    public function currency(string $currency): static
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
