<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use SeviaFlow\Core\Resources\ResourceContext;

final class DateField extends Field
{
    protected static string $type = 'date';

    private string $inputFormat = 'Y-m-d';

    public function inputFormat(string $format): static
    {
        if (trim($format) === '') {
            throw new \InvalidArgumentException('DateField input format cannot be empty.');
        }

        $this->inputFormat = $format;

        return $this;
    }

    public function getInputFormat(): string
    {
        return $this->inputFormat;
    }

    public function formatForInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($this->inputFormat);
        }

        try {
            return Carbon::parse((string) $value)->format($this->inputFormat);
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'format' => 'date',
            'nullable' => true,
            'x-seviaflow-input-format' => $this->inputFormat,
        ];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['date'];
    }
}
