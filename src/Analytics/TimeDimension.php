<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;

final class TimeDimension
{
    /**
     * @var array<int, string>
     */
    public const GRAINS = ['day', 'week', 'month', 'quarter', 'year'];

    private ?string $label = null;

    private ?string $description = null;

    /**
     * @var array<int, string>
     */
    private array $aliases = [];

    private ?string $field = null;

    /**
     * @var array<int, string>
     */
    private array $relationshipPath = [];

    /** @var array<int, string> */
    private array $relationshipResources = [];

    /**
     * @var array<int, string>
     */
    private array $grains = self::GRAINS;

    private function __construct(
        private readonly string $key,
    ) {
        self::assertKey($key, 'time dimension key');
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
                throw new InvalidArgumentException('Analytics time dimension aliases must contain only strings.');
            }

            $value = $this->normalizeNullableText($alias);
            if ($value === null) {
                throw new InvalidArgumentException('Analytics time dimension aliases cannot be empty.');
            }

            $fingerprint = $this->aliasFingerprint($value);
            if (isset($seen[$fingerprint])) {
                throw new InvalidArgumentException(sprintf('Analytics time dimension alias [%s] is duplicated.', $value));
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
        self::assertColumn($field, 'time dimension field');

        $this->field = $field;

        return $this;
    }

    /**
     * @param  array<int, string>  $relationships
     */
    public function relationshipPath(array $relationships): self
    {
        $this->relationshipPath = $this->normalizeRelationshipPath($relationships);
        $this->relationshipResources = [];

        return $this;
    }

    /**
     * Set the Resource classes that govern every hop in this relationship path.
     *
     * @param  array<int, mixed>  $resourceClasses
     */
    public function relationshipResources(array $resourceClasses): self
    {
        if ($this->relationshipPath === []) {
            throw new InvalidArgumentException('Analytics relationship resources require relationshipPath() first.');
        }

        if (count($resourceClasses) !== count($this->relationshipPath)) {
            throw new InvalidArgumentException(sprintf(
                'Analytics relationship requires one Resource class per hop; expected %d, got %d.',
                count($this->relationshipPath),
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

        $this->relationshipResources = $normalized;

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
        return $this->relationshipResources;
    }

    /**
     * @param  array<int, string>  $grains
     */
    public function grains(array $grains): self
    {
        $normalized = [];

        foreach ($grains as $grain) {
            if (! is_string($grain)) {
                throw new InvalidArgumentException('Analytics time grains must be strings.');
            }

            $value = strtolower(trim($grain));
            if (! in_array($value, self::GRAINS, true)) {
                throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain));
            }

            $normalized[] = $value;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Analytics time dimension requires at least one supported grain.');
        }

        $this->grains = array_values(array_unique($normalized));

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

    public function fieldName(): string
    {
        if (! is_string($this->field) || $this->field === '') {
            return $this->key;
        }

        return $this->field;
    }

    /**
     * @return array<int, string>
     */
    public function grainsDefinition(): array
    {
        return $this->grains;
    }

    public function supportsGrain(string $grain): bool
    {
        return in_array(strtolower(trim($grain)), $this->grains, true);
    }

    /**
     * @return array<int, string>
     */
    public function relationshipPathDefinition(): array
    {
        return $this->relationshipPath;
    }

    public function relationshipDepth(): int
    {
        return count($this->relationshipPath);
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
            'field' => $this->fieldName(),
            'relationship_path' => $this->relationshipPath,
            'relationship_resources' => $this->relationshipResources,
            'grains' => $this->grains,
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
