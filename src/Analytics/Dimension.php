<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;

final class Dimension
{
    private ?string $label = null;

    private ?string $description = null;

    /**
     * @var array<int, string>
     */
    private array $aliases = [];

    private ?string $field = null;

    /** @var array<string, mixed>|null */
    private ?array $relationship = null;

    private string $type = 'string';

    /**
     * @var array<string, string>
     */
    private array $labels = [];

    private function __construct(
        private readonly string $key,
    ) {
        self::assertKey($key, 'dimension key');
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(?string $label): self
    {
        $this->label = $label !== null ? trim($label) : null;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description !== null ? $this->normalizeNullableText($description) : null;

        return $this;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    public function aliases(array $aliases): self
    {
        $normalized = [];
        $seen = [];

        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException('Analytics dimension aliases must contain only strings.');
            }

            $value = $this->normalizeNullableText($alias);
            if ($value === null) {
                throw new InvalidArgumentException('Analytics dimension aliases cannot be empty.');
            }

            $fingerprint = $this->aliasFingerprint($value);
            if (isset($seen[$fingerprint])) {
                throw new InvalidArgumentException(sprintf('Analytics dimension alias [%s] is duplicated.', $value));
            }

            $seen[$fingerprint] = true;
            $normalized[] = $value;
        }

        $this->aliases = $normalized;

        return $this;
    }

    public function alias(string $alias): self
    {
        return $this->aliases([...$this->aliases, $alias]);
    }

    public function field(string $field): self
    {
        self::assertColumn($field, 'dimension field');

        $this->field = $field;
        $this->relationship = null;

        return $this;
    }

    public function relationship(string $relationship, string $titleAttribute): self
    {
        self::assertRelationship($relationship);
        self::assertColumn($titleAttribute, 'relationship title attribute');

        $this->relationship = [
            'path' => [$relationship],
            'key_attribute' => null,
            'label_attribute' => $titleAttribute,
            'relationship' => $relationship,
            'title_attribute' => $titleAttribute,
            'mode' => 'entity',
        ];
        $this->field = null;

        return $this;
    }

    public function relationshipField(string $relationship, string $field): self
    {
        self::assertRelationship($relationship);
        self::assertColumn($field, 'relationship field');

        $this->relationship = [
            'path' => [$relationship],
            'key_attribute' => $field,
            'label_attribute' => $field,
            'relationship' => $relationship,
            'title_attribute' => $field,
            'mode' => 'field',
        ];
        $this->field = null;

        return $this;
    }

    /**
     * @param  array<int, string>  $relationships
     */
    public function relationshipPath(array $relationships, string $keyAttribute = 'id', string $labelAttribute = 'id'): self
    {
        $path = $this->normalizeRelationshipPath($relationships);
        self::assertColumn($keyAttribute, 'relationship key attribute');
        self::assertColumn($labelAttribute, 'relationship label attribute');

        $this->relationship = [
            'path' => $path,
            'key_attribute' => $keyAttribute,
            'label_attribute' => $labelAttribute,
            'relationship' => implode('.', $path),
            'title_attribute' => $labelAttribute,
            'mode' => 'entity',
        ];
        $this->field = null;

        return $this;
    }

    public function relationshipKey(string $attribute): self
    {
        self::assertColumn($attribute, 'relationship key attribute');

        if ($this->relationship === null) {
            throw new InvalidArgumentException('Analytics relationship key requires relationship() or relationshipPath() first.');
        }

        $this->relationship['key_attribute'] = $attribute;

        return $this;
    }

    public function relationshipLabel(string $attribute): self
    {
        self::assertColumn($attribute, 'relationship label attribute');

        if ($this->relationship === null) {
            throw new InvalidArgumentException('Analytics relationship label requires relationship() or relationshipPath() first.');
        }

        $this->relationship['label_attribute'] = $attribute;
        $this->relationship['title_attribute'] = $attribute;

        return $this;
    }

    /**
     * Set the Resource classes that govern every hop in this relationship path.
     *
     * @param  array<int, mixed>  $resourceClasses
     */
    public function relationshipResources(array $resourceClasses): self
    {
        if ($this->relationship === null) {
            throw new InvalidArgumentException('Analytics relationship resources require relationship() or relationshipPath() first.');
        }

        if (count($resourceClasses) !== $this->relationshipDepth()) {
            throw new InvalidArgumentException(sprintf(
                'Analytics relationship requires one Resource class per hop; expected %d, got %d.',
                $this->relationshipDepth(),
                count($resourceClasses)
            ));
        }

        $normalized = [];
        foreach ($resourceClasses as $resourceClass) {
            if (! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Analytics relationship Resource [%s] must extend %s.',
                    is_scalar($resourceClass) ? (string) $resourceClass : get_debug_type($resourceClass),
                    Resource::class
                ));
            }

            $normalized[] = $resourceClass;
        }

        $this->relationship['resource_classes'] = $normalized;

        return $this;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function relationshipResource(string $resourceClass): self
    {
        return $this->relationshipResources([$resourceClass]);
    }

    /**
     * @return array<int, string>
     */
    public function relationshipResourceClasses(): array
    {
        return $this->relationship['resource_classes'] ?? [];
    }

    public function type(string $type): self
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, ['string', 'integer', 'number', 'money', 'date', 'boolean'], true)) {
            throw new InvalidArgumentException(sprintf('Analytics dimension type [%s] is not supported.', $type));
        }

        $this->type = $normalized;

        return $this;
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function labels(array $labels): self
    {
        $normalized = [];

        foreach ($labels as $value => $label) {
            if (! is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException('Analytics dimension labels must be non-empty strings.');
            }

            $normalized[(string) $value] = trim($label);
        }

        $this->labels = $normalized;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return is_string($this->label) && $this->label !== ''
            ? $this->label
            : str((string) str_replace(['_', '-'], ' ', $this->key))->headline()->toString();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<int, string>
     */
    public function aliasesDefinition(): array
    {
        return $this->aliases;
    }

    public function fieldName(): ?string
    {
        return $this->field;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function relationshipDefinition(): ?array
    {
        return $this->relationship;
    }

    /**
     * @return array<int, string>
     */
    public function relationshipPathDefinition(): array
    {
        return $this->relationship['path'] ?? [];
    }

    public function relationshipDepth(): int
    {
        return count($this->relationshipPathDefinition());
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function displayLabel(mixed $value, mixed $rawLabel = null): string
    {
        $key = (string) $value;

        if (array_key_exists($key, $this->labels)) {
            return $this->labels[$key];
        }

        if ($rawLabel !== null && (string) $rawLabel !== '') {
            return (string) $rawLabel;
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->getLabel(),
            'description' => $this->description,
            'aliases' => $this->aliases,
            'type' => $this->type,
            'field' => $this->field,
            'relationship' => $this->relationship,
            'labels' => $this->labels,
        ];
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }

    private static function assertColumn(string $column, string $label): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $column));
        }
    }

    private static function assertRelationship(string $relationship): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $relationship)) {
            throw new InvalidArgumentException(sprintf('Analytics relationship [%s] is invalid.', $relationship));
        }
    }

    /**
     * @param  array<int, string>  $relationships
     * @return array<int, string>
     */
    private function normalizeRelationshipPath(array $relationships): array
    {
        if ($relationships === []) {
            throw new InvalidArgumentException('Analytics relationship path cannot be empty.');
        }

        $path = [];
        foreach ($relationships as $relationship) {
            if (! is_string($relationship)) {
                throw new InvalidArgumentException('Analytics relationship path must contain only strings.');
            }

            self::assertRelationship($relationship);
            $path[] = $relationship;
        }

        return $path;
    }

    private function normalizeNullableText(string $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?: $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function aliasFingerprint(string $value): string
    {
        return mb_strtolower($value);
    }
}
