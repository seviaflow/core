<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns\Support;

use SeviaFlow\Core\Resources\Columns\BadgeColumn;

final class BadgeRenderer
{
    /**
     * @var array<string, string>
     */
    private const COLOR_CLASSES = [
        'gray' => 'bg-gray-100 text-gray-800',
        'red' => 'bg-red-100 text-red-800',
        'yellow' => 'bg-yellow-100 text-yellow-800',
        'green' => 'bg-green-100 text-green-800',
        'blue' => 'bg-blue-100 text-blue-800',
        'indigo' => 'bg-indigo-100 text-indigo-800',
        'purple' => 'bg-purple-100 text-purple-800',
        'pink' => 'bg-pink-100 text-pink-800',
    ];

    public static function render(BadgeColumn $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $key = (string) $value;
        $labels = $column->getLabels();
        $display = $labels[$key] ?? $key;

        $colorKey = $column->getColors()[$key] ?? 'gray';
        $colorClasses = self::COLOR_CLASSES[$colorKey] ?? self::COLOR_CLASSES['gray'];

        return sprintf(
            '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium %s">%s</span>',
            $colorClasses,
            e($display)
        );
    }
}
