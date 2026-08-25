<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\MoneyFilter;
use SeviaFlow\Core\Resources\Filters\NumberFilter;
use SeviaFlow\Core\Resources\Filters\NumberRangeFilter;

beforeEach(function (): void {
    Schema::create('number_filter_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->integer('quantity');
        $table->timestamps();
    });

    NumberFilterRecord::query()->create(['name' => 'Small', 'price' => 10.50, 'quantity' => 5]);
    NumberFilterRecord::query()->create(['name' => 'Medium', 'price' => 100.00, 'quantity' => 50]);
    NumberFilterRecord::query()->create(['name' => 'Large', 'price' => 500.25, 'quantity' => 500]);
});

it('number filter exact value works', function (): void {
    $query = NumberFilterRecord::query();

    NumberFilter::make('quantity')->apply($query, '50');

    expect($query->pluck('name')->all())->toBe(['Medium']);
});

it('number filter ignores empty value', function (): void {
    $query = NumberFilterRecord::query();

    NumberFilter::make('quantity')->apply($query, '');

    expect($query->pluck('name')->all())
        ->toContain('Small')
        ->toContain('Medium')
        ->toContain('Large');
});

it('number filter handles invalid numeric value safely', function (): void {
    $query = NumberFilterRecord::query();

    NumberFilter::make('quantity')->apply($query, 'abc');

    expect($query->pluck('name')->all())
        ->toContain('Small')
        ->toContain('Medium')
        ->toContain('Large');
});

it('number range filter min works', function (): void {
    $query = NumberFilterRecord::query();

    NumberRangeFilter::make('price')->apply($query, ['min' => 100]);

    expect($query->pluck('name')->all())->toBe(['Medium', 'Large']);
});

it('number range filter max works', function (): void {
    $query = NumberFilterRecord::query();

    NumberRangeFilter::make('price')->apply($query, ['max' => 100]);

    expect($query->pluck('name')->all())->toBe(['Small', 'Medium']);
});

it('number range filter min and max works', function (): void {
    $query = NumberFilterRecord::query();

    NumberRangeFilter::make('price')->apply($query, ['min' => 50, 'max' => 200]);

    expect($query->pluck('name')->all())->toBe(['Medium']);
});

it('number range filter ignores empty boundaries', function (): void {
    $query = NumberFilterRecord::query();

    NumberRangeFilter::make('price')->apply($query, ['min' => '', 'max' => null]);

    expect($query->pluck('name')->all())
        ->toContain('Small')
        ->toContain('Medium')
        ->toContain('Large');
});

it('money filter works as numeric range specialization', function (): void {
    $query = NumberFilterRecord::query();

    $filter = MoneyFilter::make('price')->currency('pln');
    $filter->apply($query, ['min' => 100, 'max' => 600]);

    expect($query->pluck('name')->all())->toBe(['Medium', 'Large']);
    expect($filter->getCurrency())->toBe('PLN');
});

it('number and range filters use safe bindings', function (): void {
    $exactQuery = NumberFilterRecord::query();
    NumberFilter::make('quantity')->apply($exactQuery, 50);

    expect($exactQuery->toSql())->toContain('= ?')
        ->and($exactQuery->getBindings())->toContain(50);

    $rangeQuery = NumberFilterRecord::query();
    NumberRangeFilter::make('price')->apply($rangeQuery, ['min' => 10, 'max' => 100]);

    expect($rangeQuery->toSql())->toContain('>= ?')
        ->and($rangeQuery->toSql())->toContain('<= ?')
        ->and($rangeQuery->getBindings())->toContain(10)
        ->and($rangeQuery->getBindings())->toContain(100);
});

final class NumberFilterRecord extends Model
{
    protected $table = 'number_filter_records';

    protected $guarded = [];
}
