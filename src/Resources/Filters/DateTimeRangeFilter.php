<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class DateTimeRangeFilter extends DateRangeFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $from = $this->parseDateTime($value['from'] ?? null);
        $to = $this->parseDateTime($value['to'] ?? null);

        if ($from !== null) {
            $query->where($this->name(), '>=', $from);
        }

        if ($to !== null) {
            $query->where($this->name(), '<=', $to);
        }

        return $query;
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
