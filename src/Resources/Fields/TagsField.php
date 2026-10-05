<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Fields\Concerns\NormalizesStringList;
use SeviaFlow\Core\Resources\ResourceContext;

final class TagsField extends Field
{
    use NormalizesStringList;

    protected static string $type = 'tags';

    private ?int $minTags = null;

    private ?int $maxTags = null;

    private int $maxTagLength = 100;

    public function minTags(int $min): static
    {
        if ($min < 0) {
            throw new \InvalidArgumentException('TagsField minimum must not be negative.');
        }

        $this->minTags = $min;

        return $this;
    }

    public function maxTags(int $max): static
    {
        if ($max < 1) {
            throw new \InvalidArgumentException('TagsField maximum must be greater than zero.');
        }

        $this->maxTags = $max;

        return $this;
    }

    public function tagLength(int $length): static
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('TagsField tag length must be greater than zero.');
        }

        $this->maxTagLength = $length;

        return $this;
    }

    public function getMinTags(): ?int
    {
        return $this->minTags;
    }

    public function getMaxTags(): ?int
    {
        return $this->maxTags;
    }

    public function getTagLength(): int
    {
        return $this->maxTagLength;
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'array',
            'items' => ['type' => 'string', 'maxLength' => $this->maxTagLength],
            'nullable' => true,
            'uniqueItems' => true,
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): array
    {
        return $this->normalizeStringList($value);
    }

    public function prepareForValidation(mixed $value): mixed
    {
        return is_array($value) || is_string($value)
            ? $this->normalizeStringList($value)
            : parent::prepareForValidation($value);
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function formatForInput(mixed $value): mixed
    {
        return is_array($value) ? implode(', ', $this->normalizeStringList($value)) : $value;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $rules = ['array'];

        if ($this->minTags !== null) {
            $rules[] = 'min:'.$this->minTags;
        }

        if ($this->maxTags !== null) {
            $rules[] = 'max:'.$this->maxTags;
        }

        return $rules;
    }

    public function defaultNestedValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['*' => ['string', 'max:'.$this->maxTagLength]];
    }
}
