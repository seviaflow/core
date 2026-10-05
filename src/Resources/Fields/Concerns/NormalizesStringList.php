<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Concerns;

trait NormalizesStringList
{
    /**
     * @return array<int, string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = $this->parseStringList($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);
            if ($item === '' || in_array($item, $normalized, true)) {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected function parseStringList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_map(static fn (mixed $item): string => (string) $item, $decoded);
        }

        return explode(',', $value);
    }
}
