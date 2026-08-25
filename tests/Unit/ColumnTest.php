<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\StatusColumn;
use SeviaFlow\Core\Resources\Columns\Support\BadgeRenderer;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\ResourceContext;

it('creates a text column with correct name', function (): void {
    $column = TextColumn::make('email');

    expect($column->name())->toBe('email');
});

it('uses human-readable label by default', function (): void {
    expect(TextColumn::make('first_name')->getLabel())->toBe('First name')
        ->and(TextColumn::make('created_at')->getLabel())->toBe('Created at');
});

it('allows explicit label override', function (): void {
    $column = TextColumn::make('name')->label('Full name');

    expect($column->getLabel())->toBe('Full name');
});

it('handles searchable and sortable flags', function (): void {
    $column = TextColumn::make('name');

    expect($column->isSearchable())->toBeFalse()
        ->and($column->isSortable())->toBeFalse();

    $column->searchable()->sortable();

    expect($column->isSearchable())->toBeTrue()
        ->and($column->isSortable())->toBeTrue();

    $column->searchable(false)->sortable(false);

    expect($column->isSearchable())->toBeFalse()
        ->and($column->isSortable())->toBeFalse();
});

it('stores searchable mode when provided', function (): void {
    $column = TextColumn::make('email')->searchable(mode: 'starts_with');

    expect($column->isSearchable())->toBeTrue()
        ->and($column->getSearchMode())->toBe('starts_with');
});

it('rejects unsupported searchable mode', function (): void {
    expect(fn () => TextColumn::make('email')->searchable(mode: 'unsupported'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported search mode');
});

it('is visible on index by default', function (): void {
    expect(TextColumn::make('email')->isVisible(ResourceContext::Index))->toBeTrue();
});

it('can be hidden on index', function (): void {
    $column = TextColumn::make('internal_notes')->hiddenOnIndex();

    expect($column->isVisible('index'))->toBeFalse();
});

it('can be explicitly visible on index', function (): void {
    $column = TextColumn::make('created_at')->hiddenOnIndex()->visibleOnIndex();

    expect($column->isVisible('index'))->toBeTrue();
});

it('stores belongsTo relationship metadata', function (): void {
    $column = BelongsToColumn::make('company')
        ->relationship('company', 'name');

    expect($column->getRelationshipName())->toBe('company')
        ->and($column->getTitleAttribute())->toBe('name');
});

it('uses id as default belongsTo title attribute', function (): void {
    $column = BelongsToColumn::make('company')
        ->relationship('company');

    expect($column->getTitleAttribute())->toBe('id');
});

it('stores badge colors and labels', function (): void {
    $column = BadgeColumn::make('status')
        ->colors([
            'active' => 'green',
            'blocked' => 'red',
        ])
        ->labels([
            'active' => 'Active',
            'blocked' => 'Blocked',
        ]);

    expect($column->getColors())->toBe([
        'active' => 'green',
        'blocked' => 'red',
    ])->and($column->getLabels())->toBe([
        'active' => 'Active',
        'blocked' => 'Blocked',
    ]);
});

it('status column options map to labels', function (): void {
    $column = StatusColumn::make('status')->options([
        'draft' => 'Draft',
        'active' => 'Active',
    ]);

    expect($column->getOptions())->toBe([
        'draft' => 'Draft',
        'active' => 'Active',
    ]);
});

it('badge renderer falls back safely for null unknown and unsafe colors', function (): void {
    $column = BadgeColumn::make('status')
        ->labels(['pending' => 'Pending'])
        ->colors(['pending' => 'not-a-safe-color']);

    $nullRendered = BadgeRenderer::render($column, null);
    $knownRendered = BadgeRenderer::render($column, 'pending');
    $unknownRendered = BadgeRenderer::render($column, 'mystery');

    expect($nullRendered)->toBe('—')
        ->and($knownRendered)->toContain('Pending', 'bg-gray-100')
        ->and($unknownRendered)->toContain('mystery', 'bg-gray-100');
});
