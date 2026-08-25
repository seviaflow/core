<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class DateRangeFilter extends Filter
{
    public function isActive(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        return (is_string($from) ? trim($from) !== '' : $from !== null)
            || (is_string($to) ? trim($to) !== '' : $to !== null);
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $from = $this->parseDate($value['from'] ?? null);
        $to = $this->parseDate($value['to'] ?? null);

        if ($from !== null) {
            $query->where($this->name(), '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where($this->name(), '<=', $to->endOfDay());
        }

        return $query;
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
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
