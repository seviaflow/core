<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics\Exceptions;

use InvalidArgumentException;

final class AnalyticsQueryTooComplexException extends InvalidArgumentException
{
    /**
     * @param  array<string, int>  $breakdown
     */
    public function __construct(
        private readonly int $score,
        private readonly int $max,
        private readonly array $breakdown,
    ) {
        parent::__construct(sprintf('Analytics query complexity score [%d] exceeds the configured max [%d].', $score, $max));
    }

    public function score(): int
    {
        return $this->score;
    }

    public function max(): int
    {
        return $this->max;
    }

    /**
     * @return array<string, int>
     */
    public function breakdown(): array
    {
        return $this->breakdown;
    }
}
