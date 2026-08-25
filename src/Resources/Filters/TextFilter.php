<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class TextFilter extends Filter
{
    public const MODE_CONTAINS = 'contains';

    public const MODE_STARTS_WITH = 'starts_with';

    public const MODE_EXACT = 'exact';

    private string $mode = self::MODE_STARTS_WITH;

    public function mode(string $mode): self
    {
        if (! in_array($mode, [self::MODE_CONTAINS, self::MODE_STARTS_WITH, self::MODE_EXACT], true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported text filter mode [%s]. Allowed modes: contains, starts_with, exact.',
                $mode
            ));
        }

        $this->mode = $mode;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        $text = trim((string) $value);

        if ($this->mode === self::MODE_EXACT) {
            return $query->where($this->name(), '=', $text);
        }

        $pattern = $this->mode === self::MODE_STARTS_WITH
            ? $text.'%'
            : '%'.$text.'%';

        return $query->where($this->name(), 'like', $pattern);
    }
}
