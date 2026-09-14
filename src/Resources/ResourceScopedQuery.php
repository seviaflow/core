<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

final class ResourceScopedQuery
{
    public const ACTIVE = 'active';

    public const TRASHED = 'trashed';

    public const WITH_TRASHED = 'with_trashed';

    /**
     * @param  class-string  $resourceClass
     * @param  Builder<Model>|null  $query
     * @return Builder<Model>
     */
    public static function make(
        string $resourceClass,
        ?Builder $query = null,
        string $softDeleteScope = self::ACTIVE
    ): Builder {
        $modelClass = $resourceClass::model();

        if ($query === null) {
            /** @var Model $model */
            $model = new $modelClass;
            $query = $model->newQuery();
        }

        if (! $query->getModel() instanceof $modelClass) {
            throw new InvalidArgumentException(sprintf(
                'Resource [%s] query must target model [%s], [%s] given.',
                $resourceClass,
                $modelClass,
                $query->getModel()::class
            ));
        }

        $scopedQuery = $resourceClass::query($query);

        if (! $scopedQuery instanceof Builder) {
            throw new InvalidArgumentException(sprintf(
                'Resource [%s] query() must return [%s], [%s] given.',
                $resourceClass,
                Builder::class,
                get_debug_type($scopedQuery)
            ));
        }

        if (! $scopedQuery->getModel() instanceof $modelClass) {
            throw new InvalidArgumentException(sprintf(
                'Resource [%s] query must target model [%s], [%s] given.',
                $resourceClass,
                $modelClass,
                $scopedQuery->getModel()::class
            ));
        }

        return self::applySoftDeleteScope($scopedQuery, $modelClass, $softDeleteScope);
    }

    /**
     * @param  class-string  $resourceClass
     */
    public static function find(
        string $resourceClass,
        int|string $recordId,
        string $softDeleteScope = self::ACTIVE,
        ?Builder $query = null
    ): ?Model {
        if ($recordId === '') {
            return null;
        }

        $record = self::make($resourceClass, $query, $softDeleteScope)
            ->whereKey($recordId)
            ->first();

        return $record instanceof Model ? $record : null;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<Model>  $modelClass
     * @return Builder<Model>
     */
    public static function applySoftDeleteScope(Builder $query, string $modelClass, string $scope): Builder
    {
        if (! in_array($scope, [self::ACTIVE, self::TRASHED, self::WITH_TRASHED], true)) {
            throw new InvalidArgumentException(sprintf('Unknown soft-delete scope [%s].', $scope));
        }

        if (! self::supportsSoftDeletes($modelClass) || $scope === self::ACTIVE) {
            return $query;
        }

        if ($scope === self::TRASHED) {
            call_user_func([$query, 'onlyTrashed']);

            return $query;
        }

        call_user_func([$query, 'withTrashed']);

        return $query;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function supportsSoftDeletes(string $modelClass): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }
}
