<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Resource;

it('can be created with make and stores name', function (): void {
    $field = BelongsToField::make('company_id');

    expect($field)->toBeInstanceOf(BelongsToField::class)
        ->and($field->name())->toBe('company_id');
});

it('stores relationship name', function (): void {
    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name');

    expect($field->getRelationshipName())->toBe('company');
});

it('stores title attribute', function (): void {
    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name');

    expect($field->getTitleAttribute())->toBe('name');
});

it('uses id as default title attribute', function (): void {
    $field = BelongsToField::make('company_id')
        ->relationship('company');

    expect($field->getTitleAttribute())->toBe('id');
});

it('uses safe default options limit', function (): void {
    $field = BelongsToField::make('company_id');

    expect($field->getOptionsLimit())->toBe(50);
});

it('supports custom options limit', function (): void {
    $field = BelongsToField::make('company_id')
        ->optionsLimit(25);

    expect($field->getOptionsLimit())->toBe(25);
});

it('supports inherited field behavior', function (): void {
    $field = BelongsToField::make('company_id')
        ->label('Company')
        ->placeholder('Select company')
        ->required();

    expect($field->getLabel())->toBe('Company')
        ->and($field->getPlaceholder())->toBe('Select company')
        ->and($field->isRequired())->toBeTrue();
});

it('rejects invalid options limit clearly', function (): void {
    $field = BelongsToField::make('company_id');

    expect(fn () => $field->optionsLimit(0))
        ->toThrow(InvalidArgumentException::class, 'BelongsToField options limit must be at least 1.');
});

it('stores searchable option columns', function (): void {
    $field = BelongsToField::make('company_id')
        ->searchableOptions(['name', 'email', 'name']);

    expect($field->getSearchableOptionColumns())->toBe(['name', 'email']);
});

it('stores query callback', function (): void {
    $field = BelongsToField::make('company_id')
        ->query(static fn ($query) => $query);

    expect($field->getQueryCallback())->not->toBeNull();
});

it('rejects invalid searchable option columns', function (): void {
    $field = BelongsToField::make('company_id');

    expect(fn () => $field->searchableOptions(['', 'name']))
        ->toThrow(InvalidArgumentException::class, 'searchable option columns');
});

it('stores inline create resource and label', function (): void {
    $field = BelongsToField::make('company_id')
        ->inlineCreate(BelongsToFieldInlineCompanyResource::class)
        ->inlineCreateLabel('Create company');

    expect($field->getInlineCreateResourceClass())->toBe(BelongsToFieldInlineCompanyResource::class)
        ->and($field->getInlineCreateLabel())->toBe('Create company');
});

it('stores dependency metadata', function (): void {
    $field = BelongsToField::make('city_id')
        ->dependsOn('country_id', static fn ($query, $countryId) => $query->where('country_id', $countryId));

    expect($field->isDependent())->toBeTrue()
        ->and($field->getDependsOnField())->toBe('country_id')
        ->and($field->getDependsOnCallback())->not->toBeNull();
});

it('rejects invalid inline create resource class', function (): void {
    expect(fn () => BelongsToField::make('company_id')->inlineCreate(stdClass::class))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

final class BelongsToFieldInlineCompanyResource extends Resource
{
    protected static string $model = BelongsToFieldInlineCompanyModel::class;
}

final class BelongsToFieldInlineCompanyModel {}
