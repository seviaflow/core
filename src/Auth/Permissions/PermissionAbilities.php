<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Permissions;

final class PermissionAbilities
{
    public const VIEW_ANY = 'viewAny';

    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const RESTORE = 'restore';

    public const FORCE_DELETE = 'forceDelete';

    public const API_DOCS_VIEW = 'apiDocs.view';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::RESTORE,
            self::FORCE_DELETE,
            self::API_DOCS_VIEW,
        ];
    }

    public static function isSupported(string $ability): bool
    {
        return in_array($ability, self::all(), true);
    }
}
