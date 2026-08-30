<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsOrder
{
    private function __construct(
        private readonly string $field,
        private readonly string $direction,
    ) {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $field)) {
            throw new InvalidArgumentException(sprintf('Analytics order field [%s] is invalid.', $field));
        }
    }

    public static function make(string $field, string $direction = 'asc'): self
    {
        $normalizedDirection = strtolower(trim($direction));

        if (! in_array($normalizedDirection, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(sprintf('Analytics order direction [%s] is not supported.', $direction));
        }

        return new self($field, $normalizedDirection);
    }

    public static function fromArray(array $payload): self
    {
        $field = $payload['field'] ?? null;
        $direction = $payload['direction'] ?? 'asc';

        if (! is_string($field) || ! is_string($direction)) {
            throw new InvalidArgumentException('Analytics order arrays require a string field and direction.');
        }

        return self::make($field, $direction);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * @return array{field:string, direction:string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'direction' => $this->direction,
        ];
    }
}
