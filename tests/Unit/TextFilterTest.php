<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Filters\TextFilter;

beforeEach(function (): void {
    Schema::create('text_filter_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    TextFilterRecord::query()->create(['name' => 'Alice']);
    TextFilterRecord::query()->create(['name' => 'Alicia']);
    TextFilterRecord::query()->create(['name' => 'Bob']);
});

it('text filter can be created', function (): void {
    expect(TextFilter::make('name'))->toBeInstanceOf(TextFilter::class);
});

it('default mode is starts_with', function (): void {
    expect(TextFilter::make('name')->getMode())->toBe(TextFilter::MODE_STARTS_WITH);
});

it('label defaults are human-readable', function (): void {
    expect(TextFilter::make('contact_email')->getLabel())->toBe('Contact email');
});

it('contains mode filters correctly', function (): void {
    $query = TextFilterRecord::query();

    TextFilter::make('name')
        ->mode(TextFilter::MODE_CONTAINS)
        ->apply($query, 'lic');

    expect($query->pluck('name')->all())->toBe(['Alice', 'Alicia']);
});

it('starts_with mode filters correctly', function (): void {
    $query = TextFilterRecord::query();

    TextFilter::make('name')
        ->mode(TextFilter::MODE_STARTS_WITH)
        ->apply($query, 'Ali');

    expect($query->pluck('name')->all())->toBe(['Alice', 'Alicia']);
});

it('exact mode filters correctly', function (): void {
    $query = TextFilterRecord::query();

    TextFilter::make('name')
        ->mode(TextFilter::MODE_EXACT)
        ->apply($query, 'Alice');

    expect($query->pluck('name')->all())->toBe(['Alice']);
});

it('empty value does not affect query', function (): void {
    $query = TextFilterRecord::query();

    TextFilter::make('name')->apply($query, '   ');

    expect($query->pluck('name')->all())
        ->toContain('Alice')
        ->toContain('Alicia')
        ->toContain('Bob');
});

it('invalid mode is rejected', function (): void {
    expect(fn () => TextFilter::make('name')->mode('unsupported'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported text filter mode');
});

it('uses sql bindings for text filter values', function (): void {
    $query = TextFilterRecord::query();

    TextFilter::make('name')
        ->mode(TextFilter::MODE_CONTAINS)
        ->apply($query, 'Alice');

    expect($query->toSql())->toContain('like ?')
        ->and($query->getBindings())->toContain('%Alice%');
});

final class TextFilterRecord extends Model
{
    protected $table = 'text_filter_records';

    protected $guarded = [];
}
