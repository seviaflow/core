<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use SeviaFlow\Core\Resources\ResourceContext;

final class DateTimeField extends Field
{
    protected static string $type = 'datetime';

    private string $inputFormat = 'Y-m-d\\TH:i';

    private ?string $timezone = null;

    public function inputFormat(string $format): static
    {
        if (trim($format) === '') {
            throw new \InvalidArgumentException('DateTimeField input format cannot be empty.');
        }

        $this->inputFormat = $format;

        return $this;
    }

    public function getInputFormat(): string
    {
        return $this->inputFormat;
    }

    public function timezone(?string $timezone): static
    {
        $this->timezone = $timezone === null ? null : trim($timezone);

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?: (string) config('app.timezone', 'UTC');
    }

    public function formatForInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $date->setTimezone($this->getTimezone())->format($this->inputFormat);
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'format' => 'date-time',
            'nullable' => true,
            'x-seviaflow-input-format' => $this->inputFormat,
            'x-seviaflow-timezone' => $this->getTimezone(),
        ];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['date', 'date_format:'.$this->inputFormat];
    }
}
