<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\MultiSelectFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;

beforeEach(function (): void {
    Schema::create('option_filter_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->default('draft');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    OptionFilterRecord::query()->create(['name' => 'Draft Active', 'status' => 'draft', 'is_active' => true]);
    OptionFilterRecord::query()->create(['name' => 'Published Inactive', 'status' => 'published', 'is_active' => false]);
    OptionFilterRecord::query()->create(['name' => 'Archived Active', 'status' => 'archived', 'is_active' => true]);
});

it('select filter works', function (): void {
    $query = OptionFilterRecord::query();

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ])
        ->apply($query, 'published');

    expect($query->pluck('name')->all())->toBe(['Published Inactive']);
});

it('select filter rejects invalid option', function (): void {
    $query = OptionFilterRecord::query();

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->apply($query, 'invalid-status');

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

it('select filter ignores empty value', function (): void {
    $query = OptionFilterRecord::query();

    SelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->apply($query, '');

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

it('multi select filter works', function (): void {
    $query = OptionFilterRecord::query();

    MultiSelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ])
        ->apply($query, ['draft', 'archived']);

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Archived Active');
    expect(in_array('Published Inactive', $query->pluck('name')->all(), true))->toBeFalse();
});

it('multi select filter rejects invalid option', function (): void {
    $query = OptionFilterRecord::query();

    MultiSelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ])
        ->apply($query, ['draft', 'unknown']);

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

it('multi select filter ignores empty array', function (): void {
    $query = OptionFilterRecord::query();

    MultiSelectFilter::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->apply($query, []);

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

it('boolean filter true works', function (): void {
    $query = OptionFilterRecord::query();

    BooleanFilter::make('is_active')->apply($query, true);

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Archived Active')
        ->toBeArray();
    expect(in_array('Published Inactive', $query->pluck('name')->all(), true))->toBeFalse();
});

it('boolean filter false works', function (): void {
    $query = OptionFilterRecord::query();

    BooleanFilter::make('is_active')->apply($query, '0');

    expect($query->pluck('name')->all())->toBe(['Published Inactive']);
});

it('boolean filter all or empty does not filter', function (): void {
    $emptyQuery = OptionFilterRecord::query();
    BooleanFilter::make('is_active')->apply($emptyQuery, '');

    expect($emptyQuery->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');

    $allQuery = OptionFilterRecord::query();
    BooleanFilter::make('is_active')->apply($allQuery, 'all');

    expect($allQuery->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

it('invalid boolean value is handled safely', function (): void {
    $query = OptionFilterRecord::query();

    BooleanFilter::make('is_active')->apply($query, 'not-a-boolean');

    expect($query->pluck('name')->all())
        ->toContain('Draft Active')
        ->toContain('Published Inactive')
        ->toContain('Archived Active');
});

final class OptionFilterRecord extends Model
{
    protected $table = 'option_filter_records';

    protected $guarded = [];
}
