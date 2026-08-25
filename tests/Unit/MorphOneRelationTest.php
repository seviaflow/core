<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Resource;

it('morphOneRelation can be created and toggled', function (): void {
    $relation = MorphOneRelation::make('avatar')
        ->resource(MorphAvatarResource::class)
        ->allowCreate()
        ->allowEdit()
        ->allowDelete();

    expect($relation->getRelationshipName())->toBe('avatar')
        ->and($relation->getResourceClass())->toBe(MorphAvatarResource::class)
        ->and($relation->canCreate())->toBeTrue()
        ->and($relation->canEdit())->toBeTrue()
        ->and($relation->canDelete())->toBeTrue();
});

it('morphOneRelation rejects invalid resource class', function (): void {
    expect(fn () => MorphOneRelation::make('avatar')->resource(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

final class MorphAvatarResource extends Resource
{
    protected static string $model = MorphAvatarModel::class;
}

final class MorphAvatarModel {}
