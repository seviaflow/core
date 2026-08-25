<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\Resource;

it('morphToManyRelation can be configured', function (): void {
    $relation = MorphToManyRelation::make('tags')
        ->resource(MorphTagResource::class)
        ->titleAttribute('name')
        ->perPage(20)
        ->optionsLimit(30)
        ->allowAttach()
        ->allowDetach();

    expect($relation->getRelationshipName())->toBe('tags')
        ->and($relation->getResourceClass())->toBe(MorphTagResource::class)
        ->and($relation->getTitleAttribute())->toBe('name')
        ->and($relation->getPerPage())->toBe(20)
        ->and($relation->getOptionsLimit())->toBe(30)
        ->and($relation->canAttach())->toBeTrue()
        ->and($relation->canDetach())->toBeTrue();
});

it('morphToManyRelation rejects invalid resource class', function (): void {
    expect(fn () => MorphToManyRelation::make('tags')->resource(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

final class MorphTagResource extends Resource
{
    protected static string $model = MorphTagModel::class;
}

final class MorphTagModel {}
