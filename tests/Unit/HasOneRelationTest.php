<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Resource;

it('hasOne relation can be created', function (): void {
    $relation = HasOneRelation::make('profile');

    expect($relation)->toBeInstanceOf(HasOneRelation::class)
        ->and($relation->getRelationshipName())->toBe('profile');
});

it('hasOne relation stores label and resource', function (): void {
    $relation = HasOneRelation::make('profile')
        ->label('Profile')
        ->resource(HasOneRelationProfileResource::class)
        ->allowCreate()
        ->allowEdit()
        ->allowDelete();

    expect($relation->getLabel())->toBe('Profile')
        ->and($relation->getResourceClass())->toBe(HasOneRelationProfileResource::class)
        ->and($relation->canCreate())->toBeTrue()
        ->and($relation->canEdit())->toBeTrue()
        ->and($relation->canDelete())->toBeTrue();
});

it('hasOne relation supports custom action labels', function (): void {
    $relation = HasOneRelation::make('profile')
        ->createLabel('Create profile')
        ->editLabel('Edit profile');

    expect($relation->getCreateLabel())->toBe('Create profile')
        ->and($relation->getEditLabel())->toBe('Edit profile');
});

it('hasOne relation validates input clearly', function (): void {
    expect(fn () => HasOneRelation::make(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HasOneRelation::make('profile')->resource(stdClass::class))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HasOneRelation::make('profile')->createLabel('   '))->toThrow(InvalidArgumentException::class)
        ->and(fn () => HasOneRelation::make('profile')->editLabel('   '))->toThrow(InvalidArgumentException::class);
});

final class HasOneRelationProfileResource extends Resource
{
    protected static string $model = HasOneRelationProfileModel::class;
}

final class HasOneRelationProfileModel {}
