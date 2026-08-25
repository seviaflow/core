<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\DateFilter;
use SeviaFlow\Core\Resources\Filters\DateRangeFilter;
use SeviaFlow\Core\Resources\Filters\DateTimeRangeFilter;

beforeEach(function (): void {
    Schema::create('date_filter_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->dateTime('created_at');
        $table->dateTime('published_at')->nullable();
    });

    DateFilterRecord::query()->create([
        'name' => 'Jan 1 morning',
        'created_at' => '2026-01-01 08:30:00',
        'published_at' => '2026-01-10 09:00:00',
    ]);

    DateFilterRecord::query()->create([
        'name' => 'Jan 1 evening',
        'created_at' => '2026-01-01 20:15:00',
        'published_at' => '2026-01-20 14:00:00',
    ]);

    DateFilterRecord::query()->create([
        'name' => 'Jan 15 noon',
        'created_at' => '2026-01-15 12:00:00',
        'published_at' => '2026-01-25 16:30:00',
    ]);

    DateFilterRecord::query()->create([
        'name' => 'Feb 1 morning',
        'created_at' => '2026-02-01 07:00:00',
        'published_at' => '2026-02-01 07:05:00',
    ]);
});

it('date filter filters exact date', function (): void {
    $query = DateFilterRecord::query();

    DateFilter::make('created_at')->apply($query, '2026-01-01');

    expect($query->pluck('name')->all())->toBe(['Jan 1 morning', 'Jan 1 evening']);
});

it('date range filter from only works', function (): void {
    $query = DateFilterRecord::query();

    DateRangeFilter::make('created_at')->apply($query, ['from' => '2026-01-15']);

    expect($query->pluck('name')->all())->toBe(['Jan 15 noon', 'Feb 1 morning']);
});

it('date range filter to only works', function (): void {
    $query = DateFilterRecord::query();

    DateRangeFilter::make('created_at')->apply($query, ['to' => '2026-01-01']);

    expect($query->pluck('name')->all())->toBe(['Jan 1 morning', 'Jan 1 evening']);
});

it('date range filter from and to works', function (): void {
    $query = DateFilterRecord::query();

    DateRangeFilter::make('created_at')->apply($query, ['from' => '2026-01-01', 'to' => '2026-01-31']);

    expect($query->pluck('name')->all())
        ->toContain('Jan 1 morning')
        ->toContain('Jan 1 evening')
        ->toContain('Jan 15 noon');
    expect(in_array('Feb 1 morning', $query->pluck('name')->all(), true))->toBeFalse();
});

it('date time range filter works', function (): void {
    $query = DateFilterRecord::query();

    DateTimeRangeFilter::make('published_at')->apply($query, [
        'from' => '2026-01-20 00:00:00',
        'to' => '2026-01-31 23:59:59',
    ]);

    expect($query->pluck('name')->all())->toBe(['Jan 1 evening', 'Jan 15 noon']);
});

it('invalid date is handled safely', function (): void {
    $query = DateFilterRecord::query();

    DateFilter::make('created_at')->apply($query, 'not-a-date');

    expect($query->pluck('name')->all())
        ->toContain('Jan 1 morning')
        ->toContain('Jan 1 evening')
        ->toContain('Jan 15 noon')
        ->toContain('Feb 1 morning');
});

it('empty range does not alter query', function (): void {
    $query = DateFilterRecord::query();

    DateRangeFilter::make('created_at')->apply($query, ['from' => '', 'to' => null]);

    expect($query->pluck('name')->all())
        ->toContain('Jan 1 morning')
        ->toContain('Jan 1 evening')
        ->toContain('Jan 15 noon')
        ->toContain('Feb 1 morning');
});

it('date filtering on datetime columns uses safe index friendly range bindings', function (): void {
    $query = DateFilterRecord::query();

    DateFilter::make('created_at')->apply($query, '2026-01-01');

    expect($query->toSql())->toContain('>= ?')
        ->and($query->toSql())->toContain('<= ?')
        ->and($query->getBindings())->toHaveCount(2);

    expect(str_contains($query->toSql(), 'date('))->toBeFalse();
});

final class DateFilterRecord extends Model
{
    protected $table = 'date_filter_records';

    public $timestamps = false;

    protected $guarded = [];
}
