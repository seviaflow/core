<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Fields\Support\RichTextSanitizer;
use SeviaFlow\Core\Resources\ResourceContext;

final class RichTextField extends Field
{
    protected static string $type = 'rich-text';

    private int $maxBytes = 65536;

    public function maxBytes(int $bytes): static
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('RichTextField byte limit must be greater than zero.');
        }

        $this->maxBytes = $bytes;

        return $this;
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function getStorageFormat(): string
    {
        return 'sanitized-html';
    }

    /** @return array<int, string> */
    public function getAllowedElements(): array
    {
        return ['p', 'h2', 'h3', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'br'];
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'contentMediaType' => 'text/html',
            'nullable' => true,
            'x-seviaflow-richtext' => [
                'format' => $this->getStorageFormat(),
                'allowedElements' => $this->getAllowedElements(),
                'allowedProtocols' => ['http', 'https', 'mailto'],
                'maxBytes' => $this->maxBytes,
            ],
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new \InvalidArgumentException('RichTextField value must be sanitized HTML text.');
        }

        $this->assertSize($value);
        $sanitized = $this->sanitizer()->sanitize($value);

        return $sanitized === '' ? null : $sanitized;
    }

    public function prepareForValidation(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || strlen($value) > $this->maxBytes) {
            return $value;
        }

        $sanitized = $this->sanitizer()->sanitize($value);

        return $sanitized === '' ? null : $sanitized;
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

        return is_string($value) ? $this->sanitizer()->sanitize($value) : '';
    }

    public function sanitizeForDisplay(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return $this->sanitizer()->sanitize($value);
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                if (strlen($value) > $this->maxBytes) {
                    $fail('The '.$attribute.' field may not exceed '.$this->maxBytes.' bytes.');
                }
            },
        ];
    }

    private function assertSize(string $value): void
    {
        if (strlen($value) > $this->maxBytes) {
            throw new \InvalidArgumentException('RichTextField value exceeds the configured byte limit.');
        }
    }

    private function sanitizer(): RichTextSanitizer
    {
        return new RichTextSanitizer;
    }
}
