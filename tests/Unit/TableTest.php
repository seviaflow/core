<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Table;

it('registers columns', function (): void {
    $nameColumn = TextColumn::make('name');
    $activeColumn = BooleanColumn::make('is_active');

    $table = (new Table)->columns([$nameColumn, $activeColumn]);

    expect($table->getColumns())
        ->toHaveCount(2)
        ->and($table->getColumns()[0])->toBe($nameColumn)
        ->and($table->getColumns()[1])->toBe($activeColumn);
});

it('rejects invalid column definitions', function (): void {
    expect(fn () => (new Table)->columns([TextColumn::make('name'), 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});

it('registers filters', function (): void {
    $activeFilter = BooleanFilter::make('is_active');

    $table = (new Table)->filters([$activeFilter]);

    expect($table->getFilters())
        ->toHaveCount(1)
        ->and($table->getFilters()[0])->toBe($activeFilter);
});

it('rejects invalid filter definitions', function (): void {
    expect(fn () => (new Table)->filters([BooleanFilter::make('is_active'), 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});

it('enables select-only-visible-columns by default', function (): void {
    expect((new Table)->shouldSelectOnlyVisibleColumns())->toBeTrue();
});

it('allows disabling select-only-visible-columns optimization', function (): void {
    $table = (new Table)->selectOnlyVisibleColumns(false);

    expect($table->shouldSelectOnlyVisibleColumns())->toBeFalse();
});
