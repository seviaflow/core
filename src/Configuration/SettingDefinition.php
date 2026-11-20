<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Configuration;

use InvalidArgumentException;

final class SettingDefinition
{
    /**
     * @param  array<int, mixed>  $rules
     * @param  array<int, mixed>  $itemRules
     */
    public function __construct(
        public readonly string $key,
        public readonly SettingType $type,
        public readonly mixed $default = null,
        public readonly bool $nullable = false,
        public readonly string $group = 'general',
        public readonly ?string $label = null,
        public readonly array $rules = [],
        public readonly ?string $readAbility = null,
        public readonly ?string $writeAbility = null,
        public readonly bool $secret = false,
        public readonly ?string $configKey = null,
        public readonly array $itemRules = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/D', $key)) {
            throw new InvalidArgumentException('Setting keys must be simple lowercase identifiers.');
        }

        if (! $this->accepts($default)) {
            throw new InvalidArgumentException('Setting default does not match its declared type.');
        }
    }

    public function accepts(mixed $value): bool
    {
        return $value === null ? $this->nullable : $this->type->accepts($value);
    }
}
