<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Resource;

it('belongsToManyRelation can be created', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation)->toBeInstanceOf(BelongsToManyRelation::class);
});

it('stores relationship name', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation->getRelationshipName())->toBe('roles');
});

it('uses human-readable label by default', function (): void {
    $relation = BelongsToManyRelation::make('user_roles');

    expect($relation->getLabel())->toBe('User roles');
});

it('stores resource class', function (): void {
    $relation = BelongsToManyRelation::make('roles')
        ->resource(RelationRoleResource::class);

    expect($relation->getResourceClass())->toBe(RelationRoleResource::class);
});

it('rejects invalid resource class', function (): void {
    expect(fn () => BelongsToManyRelation::make('roles')->resource(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

it('uses safe default title attribute', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation->getTitleAttribute())->toBe('id');
});

it('supports custom title attribute', function (): void {
    $relation = BelongsToManyRelation::make('roles')
        ->titleAttribute('name');

    expect($relation->getTitleAttribute())->toBe('name');
});

it('uses safe default and custom per page', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation->getPerPage())->toBe(10);

    $relation->perPage(25);

    expect($relation->getPerPage())->toBe(25);
});

it('uses safe default and custom options limit', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation->getOptionsLimit())->toBe(50);

    $relation->optionsLimit(75);

    expect($relation->getOptionsLimit())->toBe(75);
});

it('allows toggling attach and detach capabilities', function (): void {
    $relation = BelongsToManyRelation::make('roles');

    expect($relation->canAttach())->toBeTrue()
        ->and($relation->canDetach())->toBeTrue();

    $relation->allowAttach(false)->allowDetach(false);

    expect($relation->canAttach())->toBeFalse()
        ->and($relation->canDetach())->toBeFalse();
});

it('stores configured pivot columns', function (): void {
    $relation = BelongsToManyRelation::make('roles')
        ->pivotColumns([
            TextColumn::make('quantity')->label('Quantity'),
            TextColumn::make('discount')->label('Discount'),
        ]);

    expect($relation->getPivotColumns())->toHaveCount(2)
        ->and($relation->getPivotColumns()[0]->name())->toBe('quantity')
        ->and($relation->getPivotColumns()[1]->name())->toBe('discount');
});

it('stores configured pivot fields', function (): void {
    $relation = BelongsToManyRelation::make('roles')
        ->pivotFields([
            NumberField::make('quantity')->label('Quantity')->required(),
            SelectField::make('status')->label('Status')->options([
                'active' => 'Active',
                'paused' => 'Paused',
            ]),
        ]);

    expect($relation->getPivotFields())->toHaveCount(2)
        ->and($relation->getPivotFields()[0]->name())->toBe('quantity')
        ->and($relation->getPivotFields()[1]->name())->toBe('status');
});

final class RelationRoleResource extends Resource
{
    protected static string $model = RelationRoleModel::class;
}

final class RelationRoleModel {}
