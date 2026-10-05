<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;

final class MoneyField extends NumberField
{
    protected static string $type = 'money';

    private ?string $currency = null;

    private int $scale = 2;

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

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        return $this->normalizeStringValue($value);
    }

    public function scale(int $scale): static
    {
        if ($scale < 0 || $scale > 8) {
            throw new \InvalidArgumentException('MoneyField scale must be between 0 and 8.');
        }

        $this->scale = $scale;

        $this->precision($scale);

        return $this;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'format' => 'decimal',
            'nullable' => true,
            'x-seviaflow-money' => [
                'currency' => $this->getCurrency(),
                'scale' => $this->getScale(),
            ],
        ];
    }
}
