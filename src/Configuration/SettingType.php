<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Configuration;

enum SettingType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Number = 'number';
    case String = 'string';
    case List = 'list';
    case Map = 'map';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::Boolean => is_bool($value),
            self::Integer => is_int($value),
            self::Number => is_int($value) || is_float($value),
            self::String => is_string($value),
            self::List => is_array($value) && array_is_list($value),
            self::Map => is_array($value) && ($value === [] || ! array_is_list($value)),
        };
    }
}
