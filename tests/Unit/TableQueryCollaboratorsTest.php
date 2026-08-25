<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Tables\Queries\ResolvesPagination;
use SeviaFlow\Core\Tables\Queries\SelectsTableColumns;

it('selects table columns with primary key, visible columns, sortable and filters', function (): void {
    $resolver = new SelectsTableColumns;

    $columns = $resolver->resolve(
        'id',
        [
            TextColumn::make('name'),
            TextColumn::make('unsafe-column'),
            TextColumn::make('email'),
        ],
        [
            BooleanFilter::make('is_active'),
            SelectFilter::make('status')->options(['draft' => 'Draft']),
        ],
        'name',
        true
    );

    expect($columns)->toContain('id')
        ->and($columns)->toContain('name')
        ->and($columns)->toContain('email')
        ->and($columns)->toContain('is_active')
        ->and($columns)->toContain('status')
        ->and($columns)->not->toContain('unsafe-column');
});

it('normalizes per page with default and max guards', function (): void {
    config()->set('seviaflow.performance.pagination.default_per_page', 15);
    config()->set('seviaflow.performance.pagination.max_per_page', 50);

    $pagination = new ResolvesPagination;

    expect($pagination->normalizePerPage(0))->toBe(15)
        ->and($pagination->normalizePerPage(-10))->toBe(15)
        ->and($pagination->normalizePerPage(20))->toBe(20)
        ->and($pagination->normalizePerPage(999))->toBe(50);
});
