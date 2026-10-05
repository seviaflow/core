<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class KeyValueField extends Field
{
    protected static string $type = 'key-value';

    private ?int $maxEntries = null;

    private int $maxKeyLength = 100;

    private int $maxValueLength = 1000;

    public function maxEntries(int $max): static
    {
        if ($max < 1) {
            throw new \InvalidArgumentException('KeyValueField maximum must be greater than zero.');
        }

        $this->maxEntries = $max;

        return $this;
    }

    public function keyLength(int $length): static
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('KeyValueField key length must be greater than zero.');
        }

        $this->maxKeyLength = $length;

        return $this;
    }

    public function valueLength(int $length): static
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('KeyValueField value length must be greater than zero.');
        }

        $this->maxValueLength = $length;

        return $this;
    }

    public function getMaxEntries(): ?int
    {
        return $this->maxEntries;
    }

    public function getKeyLength(): int
    {
        return $this->maxKeyLength;
    }

    public function getValueLength(): int
    {
        return $this->maxValueLength;
    }

    protected function defaultApiSchema(): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string', 'maxLength' => $this->maxValueLength],
            'nullable' => true,
            'x-seviaflow-value-shape' => 'map<string,string>',
        ];

        if ($this->maxEntries !== null) {
            $schema['maxProperties'] = $this->maxEntries;
        }

        return $schema;
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            if (str_starts_with(ltrim($value), '[')) {
                throw new \InvalidArgumentException('Key/value data must be a JSON object with unique string keys.');
            }

            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Key/value data must be a valid JSON object.');
            }

            if ($decoded === [] && str_starts_with(ltrim($value), '{')) {
                return (object) [];
            }

            $value = $decoded;
        }

        if ($value instanceof \stdClass && get_object_vars($value) === []) {
            return $value;
        }

        if ($value === []) {
            return (object) [];
        }

        return $this->normalizeMap($value);
    }

    public function prepareForValidation(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if (str_starts_with($value, '[')) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && $decoded === [] && str_starts_with($value, '{')) {
            return (object) [];
        }

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $value;
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function formatForInput(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return '{}';
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof \stdClass) {
                    if (get_object_vars($value) !== []) {
                        $fail('The '.$attribute.' field must be a flat JSON object.');
                    }

                    return;
                }

                if (! is_array($value)) {
                    return;
                }

                if ($value !== [] && array_is_list($value)) {
                    $fail('The '.$attribute.' field must be a JSON object with unique string keys.');

                    return;
                }

                if ($this->maxEntries !== null && count($value) > $this->maxEntries) {
                    $fail('The '.$attribute.' field may not contain more than '.$this->maxEntries.' entries.');
                }

                foreach ($value as $key => $item) {
                    if (trim((string) $key) === '' || mb_strlen((string) $key) > $this->maxKeyLength) {
                        $fail('The '.$attribute.' field contains an invalid key.');

                        break;
                    }

                    if (! is_string($item) || mb_strlen($item) > $this->maxValueLength) {
                        $fail('The '.$attribute.' field values must be strings within the configured limit.');

                        break;
                    }
                }
            },
        ];
    }

    public function defaultNestedValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['*' => ['string', 'max:'.$this->maxValueLength]];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeMap(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException('Key/value data must be a JSON object with unique string keys.');
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (! is_string($item) || trim((string) $key) === '') {
                throw new \InvalidArgumentException('Key/value data must contain only string keys and values.');
            }

            $normalized[trim((string) $key)] = trim($item);
        }

        return $normalized;
    }
}
