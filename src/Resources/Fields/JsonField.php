<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class JsonField extends Field
{
    protected static string $type = 'json';

    private string $rootType = 'any';

    private int $maxBytes = 65536;

    private int $maxDepth = 8;

    public function rootType(string $type): static
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['any', 'object', 'array', 'scalar'], true)) {
            throw new \InvalidArgumentException('JsonField root type must be any, object, array, or scalar.');
        }

        $this->rootType = $type;

        return $this;
    }

    public function maxBytes(int $bytes): static
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('JsonField byte limit must be greater than zero.');
        }

        $this->maxBytes = $bytes;

        return $this;
    }

    public function maxDepth(int $depth): static
    {
        if ($depth < 1 || $depth > 512) {
            throw new \InvalidArgumentException('JsonField depth must be between 1 and 512.');
        }

        $this->maxDepth = $depth;

        return $this;
    }

    public function getRootType(): string
    {
        return $this->rootType;
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    protected function defaultApiSchema(): array
    {
        $schema = [
            'nullable' => true,
            'x-seviaflow-json' => [
                'root' => $this->rootType,
                'maxBytes' => $this->maxBytes,
                'maxDepth' => $this->maxDepth,
            ],
        ];

        return $this->rootType === 'object'
            ? array_merge($schema, ['type' => 'object'])
            : ($this->rootType === 'array'
                ? array_merge($schema, ['type' => 'array'])
                : ($this->rootType === 'scalar'
                    ? array_merge($schema, ['oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'number'],
                        ['type' => 'boolean'],
                        ['type' => 'null'],
                    ]])
                    : array_merge($schema, ['oneOf' => [
                        ['type' => 'object'],
                        ['type' => 'array'],
                        ['type' => 'string'],
                        ['type' => 'number'],
                        ['type' => 'boolean'],
                        ['type' => 'null'],
                    ]])));
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $value = $this->decode($value);
        }

        $this->assertValue($value);

        return $value;
    }

    public function prepareForValidation(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (trim($value) === '') {
            return null;
        }

        try {
            return $this->decode($value);
        } catch (\InvalidArgumentException) {
            return $value;
        }
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function formatForInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($this->rootType === 'object' && $value === []) {
            return '{}';
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null) {
                    return;
                }

                try {
                    $decoded = is_string($value) ? $this->decode($value) : $value;
                    $this->assertValue($decoded);
                } catch (\InvalidArgumentException $exception) {
                    $fail('The '.$attribute.' field '.$exception->getMessage());
                }
            },
        ];
    }

    private function decode(string $value): mixed
    {
        $value = trim($value);
        if (strlen($value) > $this->maxBytes) {
            throw new \InvalidArgumentException('exceeds the JSON size limit.');
        }

        try {
            $decoded = json_decode($value, true, $this->maxDepth, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if ($exception->getCode() === JSON_ERROR_DEPTH) {
                throw new \InvalidArgumentException('exceeds the JSON depth limit.');
            }

            throw new \InvalidArgumentException('must contain valid JSON.');
        }

        if (str_starts_with($value, '{') && $decoded === []) {
            return (object) [];
        }

        if ($this->rootType === 'object' && str_starts_with($value, '[') && $decoded === []) {
            throw new \InvalidArgumentException('must use the configured object root type.');
        }

        $this->assertDepth($decoded);

        return $decoded;
    }

    private function assertValue(mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $valid = match ($this->rootType) {
            'object' => $value instanceof \stdClass || (is_array($value) && ($value === [] || ! array_is_list($value))),
            'array' => is_array($value) && array_is_list($value),
            'scalar' => is_scalar($value),
            default => true,
        };

        if (! $valid) {
            throw new \InvalidArgumentException('must use the configured '.$this->rootType.' root type.');
        }

        $encoded = json_encode($value);
        if (is_string($encoded) && strlen($encoded) > $this->maxBytes) {
            throw new \InvalidArgumentException('exceeds the JSON size limit.');
        }
    }

    private function assertDepth(mixed $value, int $depth = 1): void
    {
        if ($value instanceof \stdClass) {
            return;
        }

        if (! is_array($value)) {
            return;
        }

        if ($depth > $this->maxDepth) {
            throw new \InvalidArgumentException('exceeds the JSON depth limit.');
        }

        foreach ($value as $item) {
            $this->assertDepth($item, $depth + 1);
        }
    }
}
