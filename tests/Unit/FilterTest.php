<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;

beforeEach(function (): void {
    Schema::create('filter_test_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->default('draft');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    FilterTestRecord::query()->create([
        'name' => 'Active Draft',
        'status' => 'draft',
        'is_active' => true,
    ]);
    FilterTestRecord::query()->create([
        'name' => 'Inactive Published',
        'status' => 'published',
        'is_active' => false,
    ]);
});

it('base filter stores name', function (): void {
    expect(BooleanFilter::make('is_active')->name())->toBe('is_active');
});

it('label defaults are human-readable', function (): void {
    expect(SelectFilter::make('account_status')->getLabel())->toBe('Account status');
});

it('explicit label works', function (): void {
    expect(BooleanFilter::make('is_active')->label('Active only')->getLabel())->toBe('Active only');
});

it('placeholder and default are stored', function (): void {
    $filter = SelectFilter::make('status')
        ->placeholder('Any status')
        ->default('draft');

    expect($filter->getPlaceholder())->toBe('Any status')
        ->and($filter->getDefault())->toBe('draft');
});

it('inactive empty values do not alter query', function (): void {
    $query = FilterTestRecord::query();
    BooleanFilter::make('is_active')->apply($query, '');

    expect($query->pluck('name')->all())
        ->toContain('Active Draft')
        ->toContain('Inactive Published');
});

it('invalid boolean filter values are handled safely', function (): void {
    $query = FilterTestRecord::query();
    BooleanFilter::make('is_active')->apply($query, 'definitely-invalid');

    expect($query->pluck('name')->all())
        ->toContain('Active Draft')
        ->toContain('Inactive Published');
});

it('existing boolean filter behavior still works', function (): void {
    $query = FilterTestRecord::query();
    BooleanFilter::make('is_active')->apply($query, '1');

    expect($query->pluck('name')->all())->toBe(['Active Draft']);
});

it('existing select filter behavior still works', function (): void {
    $query = FilterTestRecord::query();

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->apply($query, 'published');

    expect($query->pluck('name')->all())->toBe(['Inactive Published']);
});

it('invalid select values are handled safely', function (): void {
    $query = FilterTestRecord::query();

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->apply($query, 'unknown');

    expect($query->pluck('name')->all())
        ->toContain('Active Draft')
        ->toContain('Inactive Published');
});

final class FilterTestRecord extends Model
{
    protected $table = 'filter_test_records';

    protected $guarded = [];
}
