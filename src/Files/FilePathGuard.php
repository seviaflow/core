<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Files;

use SeviaFlow\Core\Resources\Fields\FileField;

final class FilePathGuard
{
    public static function isManaged(FileField $field, string $path): bool
    {
        if ($path === '' || str_contains($path, '://') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '') {
                return false;
            }
        }

        $directory = $field->getDirectory();
        if ($directory === '') {
            return true;
        }

        return $path === $directory || str_starts_with($path, $directory.'/');
    }
}
