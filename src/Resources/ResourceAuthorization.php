<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;

final class ResourceAuthorization
{
    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    private function __construct(
        private readonly string $resourceClass
    ) {}

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function canViewAny(?Authenticatable $user = null): bool
    {
        return $this->allows('viewAny', $this->modelClass(), $user);
    }

    public function canView(?Authenticatable $user, Model $record): bool
    {
        return $this->allows('view', $record, $user);
    }

    public function canCreate(?Authenticatable $user = null): bool
    {
        return $this->allows('create', $this->modelClass(), $user);
    }

    public function canUpdate(?Authenticatable $user, Model $record): bool
    {
        return $this->allows('update', $record, $user);
    }

    public function canDelete(?Authenticatable $user, Model $record): bool
    {
        return $this->allows('delete', $record, $user);
    }

    public function canRestore(?Authenticatable $user, Model $record): bool
    {
        return $this->allows('restore', $record, $user);
    }

    public function canForceDelete(?Authenticatable $user, Model $record): bool
    {
        return $this->allows('forceDelete', $record, $user);
    }

    public function canExecuteAction(?Authenticatable $user, Action $action, Model $record): bool
    {
        if (! $this->canUpdate($user, $record)) {
            return false;
        }

        return $action->canExecute($user, $record);
    }

    /**
     * @param  class-string<Model>|Model  $arguments
     */
    private function allows(string $ability, string|Model $arguments, ?Authenticatable $user): bool
    {
        $actor = $user ?? Auth::user();
        if (! $this->permissionChecker()->allows($actor, $this->resourceSlug(), $ability)) {
            return false;
        }

        if (Gate::getPolicyFor($this->modelClass()) === null) {
            return true;
        }

        if (! $actor instanceof Authenticatable) {
            return false;
        }

        return Gate::forUser($actor)->allows($ability, $arguments);
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->resourceClass::model();

        return $modelClass;
    }

    private function resourceSlug(): string
    {
        /** @var array<string, class-string<\SeviaFlow\Core\Resources\Resource>> $resources */
        $resources = (array) config('seviaflow.resources', []);

        foreach ($resources as $slug => $resourceClass) {
            if ($resourceClass === $this->resourceClass) {
                return $slug;
            }
        }

        return $this->resourceClass;
    }

    private function permissionChecker(): PermissionChecker
    {
        return app(PermissionChecker::class);
    }
}
