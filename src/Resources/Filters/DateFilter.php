<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class DateFilter extends Filter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        $date = $this->parseDate($value);

        if ($date === null) {
            return $query;
        }

        return $query
            ->where($this->name(), '>=', $date->startOfDay())
            ->where($this->name(), '<=', $date->endOfDay());
    }

    private function parseDate(mixed $value): ?CarbonImmutable
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
