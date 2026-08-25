<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Permissions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Auth\Roles\Role;

final class PermissionChecker
{
    /**
     * @param  class-string<Model>|string  $resource
     */
    public function allows(Authenticatable|Model|null $user, string $resource, string $ability): bool
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return true;
        }

        if (! $user instanceof Model) {
            return false;
        }

        if (! PermissionAbilities::isSupported($ability)) {
            return false;
        }

        $resourceSlug = $this->normalizeResourceSlug($resource);

        if ($resourceSlug === null) {
            return false;
        }

        return Role::query()
            ->whereHas('assignments', function ($query) use ($user): void {
                $query
                    ->where('model_type', $user->getMorphClass())
                    ->where('model_id', $user->getKey());
            })
            ->whereHas('permissions', function ($query) use ($resourceSlug, $ability): void {
                $query
                    ->where('resource', $resourceSlug)
                    ->where('ability', $ability)
                    ->where('allowed', true);
            })
            ->exists();
    }

    private function normalizeResourceSlug(string $resource): ?string
    {
        $normalized = trim($resource);

        if ($normalized === '') {
            return null;
        }

        /** @var array<string, class-string<Model>> $resources */
        $resources = (array) config('seviaflow.resources', []);

        if (array_key_exists($normalized, $resources)) {
            return $normalized;
        }

        foreach ($resources as $slug => $resourceClass) {
            if ($resourceClass === $normalized) {
                return $slug;
            }
        }

        return $normalized;
    }
}
