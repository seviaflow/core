<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\BelongsToFilter;

beforeEach(function (): void {
    Schema::create('belongs_to_filter_companies', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('belongs_to_filter_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('number');
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    foreach (range(1, 6) as $index) {
        BelongsToFilterCompany::query()->create([
            'name' => 'Company '.$index,
            'active' => $index % 2 === 0,
        ]);
    }

    BelongsToFilterOrder::query()->create(['number' => 'ORD-001', 'company_id' => 1]);
    BelongsToFilterOrder::query()->create(['number' => 'ORD-002', 'company_id' => 2]);
    BelongsToFilterOrder::query()->create(['number' => 'ORD-003', 'company_id' => 3]);
});

it('belongs to filter can be created', function (): void {
    expect(BelongsToFilter::make('company_id'))->toBeInstanceOf(BelongsToFilter::class);
});

it('relationship metadata is stored', function (): void {
    $filter = BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->optionsLimit(25);

    expect($filter->getRelationshipName())->toBe('company')
        ->and($filter->getTitleAttribute())->toBe('name')
        ->and($filter->getOptionsLimit())->toBe(25);
});

it('related options are limited', function (): void {
    $options = BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->optionsLimit(3)
        ->getOptionsFor(BelongsToFilterOrder::class);

    expect($options)->toHaveCount(3);
});

it('query customization filters options', function (): void {
    $options = BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->query(static fn ($query) => $query->where('active', true))
        ->optionsLimit(10)
        ->getOptionsFor(BelongsToFilterOrder::class);

    expect($options)->toHaveCount(3)
        ->and(array_values($options))->toContain('Company 2')
        ->and(array_values($options))->toContain('Company 4')
        ->and(array_values($options))->toContain('Company 6');
});

it('selected related id filters records', function (): void {
    $query = BelongsToFilterOrder::query();

    BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->apply($query, '2');

    expect($query->pluck('number')->all())->toBe(['ORD-002']);
});

it('empty value does not filter', function (): void {
    $query = BelongsToFilterOrder::query();

    BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->apply($query, '');

    expect($query->pluck('number')->all())
        ->toContain('ORD-001')
        ->toContain('ORD-002')
        ->toContain('ORD-003');
});

it('invalid related id is handled safely', function (): void {
    $query = BelongsToFilterOrder::query();

    BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->apply($query, '999999');

    expect($query->pluck('number')->all())
        ->toContain('ORD-001')
        ->toContain('ORD-002')
        ->toContain('ORD-003');
});

it('does not load full related table when resolving options', function (): void {
    foreach (range(7, 120) as $index) {
        BelongsToFilterCompany::query()->create([
            'name' => 'Company '.$index,
            'active' => true,
        ]);
    }

    $options = BelongsToFilter::make('company_id')
        ->relationship('company', 'name')
        ->optionsLimit(50)
        ->getOptionsFor(BelongsToFilterOrder::class);

    expect($options)->toHaveCount(50);
});

final class BelongsToFilterOrder extends Model
{
    protected $table = 'belongs_to_filter_orders';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToFilterCompany::class, 'company_id');
    }
}

final class BelongsToFilterCompany extends Model
{
    protected $table = 'belongs_to_filter_companies';

    protected $guarded = [];
}
