<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables\Queries;

final class ResolvesPagination
{
    public function normalizePerPage(int $perPage): int
    {
        $default = (int) config('seviaflow.performance.pagination.default_per_page', 15);
        $max = max(1, (int) config('seviaflow.performance.pagination.max_per_page', 100));

        if ($perPage < 1) {
            return min($default, $max);
        }

        return min($perPage, $max);
    }
}
