<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Resource;

it('morphManyRelation can be configured', function (): void {
    $relation = MorphManyRelation::make('comments')
        ->resource(MorphCommentResource::class)
        ->perPage(20)
        ->allowCreate()
        ->allowEdit()
        ->allowDelete()
        ->allowRestore();

    expect($relation->getRelationshipName())->toBe('comments')
        ->and($relation->getResourceClass())->toBe(MorphCommentResource::class)
        ->and($relation->getPerPage())->toBe(20)
        ->and($relation->canCreate())->toBeTrue()
        ->and($relation->canEdit())->toBeTrue()
        ->and($relation->canDelete())->toBeTrue()
        ->and($relation->canRestore())->toBeTrue();
});

it('morphManyRelation rejects invalid resource class', function (): void {
    expect(fn () => MorphManyRelation::make('comments')->resource(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

final class MorphCommentResource extends Resource
{
    protected static string $model = MorphCommentModel::class;
}

final class MorphCommentModel {}
