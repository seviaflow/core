<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Database\Eloquent\Builder;
use SeviaFlow\Core\Exceptions\ResourceModelNotConfiguredException;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\Workflows\Workflow;

abstract class Resource
{
    protected static string $model;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationIcon = null;

    protected static ?int $navigationSort = null;

    protected static bool $shouldRegisterNavigation = true;

    public static function model(): string
    {
        if (! isset(static::$model) || static::$model === '') {
            throw ResourceModelNotConfiguredException::forResource(static::class);
        }

        return static::$model;
    }

    public static function navigationLabel(): ?string
    {
        return static::$navigationLabel;
    }

    public static function navigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function navigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function navigationSort(): ?int
    {
        return static::$navigationSort;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function show(Form $form): Form
    {
        return static::form($form);
    }

    public static function api(ApiResource $api): ApiResource
    {
        return $api;
    }

    public static function export(ExportResource $export): ExportResource
    {
        return $export;
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import;
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events;
    }

    /**
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, BulkAction>
     */
    public static function bulkActions(): array
    {
        return [];
    }

    /**
     * @return array<int, HasOneRelation|HasManyRelation|BelongsToManyRelation|MorphOneRelation|MorphManyRelation|MorphToManyRelation>
     */
    public static function relations(): array
    {
        return [];
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow;
    }

    /**
     * Modify the base table query before search, filters, sorting and pagination.
     */
    public static function query(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @deprecated Use query() instead.
     */
    public static function tableQuery(Builder $query): Builder
    {
        return static::query($query);
    }
}
