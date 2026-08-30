<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class Dimension
{
    private ?string $label = null;

    private ?string $field = null;

    /**
     * @var array{relationship:string, title_attribute:string, mode:string}|null
     */
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
            'relationship' => $relationship,
            'title_attribute' => $field,
            'mode' => 'field',
        ];
        $this->field = null;

        return $this;
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

    public function fieldName(): ?string
    {
        return $this->field;
    }

    /**
     * @return array{relationship:string, title_attribute:string, mode:string}|null
     */
    public function relationshipDefinition(): ?array
    {
        return $this->relationship;
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
}
