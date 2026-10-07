<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Operations;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ResourceRevision
{
    public static function for(Model $record): string
    {
        $attributes = $record->getRawOriginal();
        ksort($attributes);

        return hash('sha256', serialize($attributes));
    }

    public static function fromIfMatch(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $value = trim($header);
        if (str_contains($value, ',')) {
            throw new InvalidArgumentException('If-Match must contain one SeviaFlow revision token.');
        }

        if (str_starts_with($value, 'W/')) {
            throw new InvalidArgumentException('Weak If-Match validators are not supported for resource updates.');
        }

        $value = trim($value, '"');
        if (! preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException('If-Match must contain the revision token returned in ETag.');
        }

        return $value;
    }
}
