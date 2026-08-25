<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;

it('can be created', function (): void {
    $relation = HasManyRelation::make('orders');

    expect($relation)->toBeInstanceOf(HasManyRelation::class);
});

it('stores relationship name', function (): void {
    $relation = HasManyRelation::make('orders');

    expect($relation->getRelationshipName())->toBe('orders');
});

it('uses human-readable label by default', function (): void {
    $relation = HasManyRelation::make('user_orders');

    expect($relation->getLabel())->toBe('User orders');
});

it('uses explicit label', function (): void {
    $relation = HasManyRelation::make('orders')->label('Customer Orders');

    expect($relation->getLabel())->toBe('Customer Orders');
});

it('stores resource class', function (): void {
    $relation = HasManyRelation::make('orders')
        ->resource(RelationOrderResource::class);

    expect($relation->getResourceClass())->toBe(RelationOrderResource::class);
});

it('rejects invalid resource class', function (): void {
    expect(fn () => HasManyRelation::make('orders')->resource(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

it('uses safe default per page', function (): void {
    $relation = HasManyRelation::make('orders');

    expect($relation->getPerPage())->toBe(10);
});

it('supports custom per page', function (): void {
    $relation = HasManyRelation::make('orders')->perPage(25);

    expect($relation->getPerPage())->toBe(25);
});

it('create and edit are disabled by default', function (): void {
    $relation = HasManyRelation::make('orders');

    expect($relation->canCreate())->toBeFalse()
        ->and($relation->canEdit())->toBeFalse();
});

it('supports enabling create and edit with custom labels', function (): void {
    $relation = HasManyRelation::make('orders')
        ->allowCreate()
        ->allowEdit()
        ->createLabel('Add order')
        ->editLabel('Edit order');

    expect($relation->canCreate())->toBeTrue()
        ->and($relation->canEdit())->toBeTrue()
        ->and($relation->getCreateLabel())->toBe('Add order')
        ->and($relation->getEditLabel())->toBe('Edit order');
});

it('delete and restore are disabled by default', function (): void {
    $relation = HasManyRelation::make('orders');

    expect($relation->canDelete())->toBeFalse()
        ->and($relation->canRestore())->toBeFalse();
});

it('supports enabling delete and restore', function (): void {
    $relation = HasManyRelation::make('orders')
        ->allowDelete()
        ->allowRestore();

    expect($relation->canDelete())->toBeTrue()
        ->and($relation->canRestore())->toBeTrue();
});

final class RelationOrderResource extends Resource
{
    protected static string $model = RelationOrderModel::class;
}

final class RelationOrderModel {}
