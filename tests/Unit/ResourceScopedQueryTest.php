<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceScopedQuery;

beforeEach(function (): void {
    Schema::create('resource_scoped_query_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('allowed')->default(false);
        $table->softDeletes();
        $table->timestamps();
    });
});

it('applies the resource scope to record lookup for integer keys', function (): void {
    $allowed = ResourceScopedQueryRecord::query()->create(['name' => 'Allowed', 'allowed' => true]);
    $forbidden = ResourceScopedQueryRecord::query()->create(['name' => 'Forbidden', 'allowed' => false]);

    expect(ResourceScopedQuery::find(ResourceScopedQueryResource::class, $allowed->getKey())?->getKey())
        ->toBe($allowed->getKey())
        ->and(ResourceScopedQuery::find(ResourceScopedQueryResource::class, $forbidden->getKey()))
        ->toBeNull();
});

it('keeps soft-delete visibility explicit', function (): void {
    $active = ResourceScopedQueryRecord::query()->create(['name' => 'Active', 'allowed' => true]);
    $trashed = ResourceScopedQueryRecord::query()->create(['name' => 'Trashed', 'allowed' => true]);
    $trashed->delete();

    expect(ResourceScopedQuery::make(ResourceScopedQueryResource::class)->count())
        ->toBe(1)
        ->and(ResourceScopedQuery::make(ResourceScopedQueryResource::class, null, ResourceScopedQuery::TRASHED)->count())
        ->toBe(1)
        ->and(ResourceScopedQuery::make(ResourceScopedQueryResource::class, null, ResourceScopedQuery::WITH_TRASHED)->count())
        ->toBe(2)
        ->and(ResourceScopedQuery::find(ResourceScopedQueryResource::class, $active->getKey(), ResourceScopedQuery::TRASHED))
        ->toBeNull()
        ->and(ResourceScopedQuery::find(ResourceScopedQueryResource::class, $trashed->getKey(), ResourceScopedQuery::WITH_TRASHED)?->getKey())
        ->toBe($trashed->getKey());
});

final class ResourceScopedQueryRecord extends Model
{
    use SoftDeletes;

    protected $table = 'resource_scoped_query_records';

    protected $guarded = [];
}

final class ResourceScopedQueryResource extends Resource
{
    protected static string $model = ResourceScopedQueryRecord::class;

    public static function query(Builder $query): Builder
    {
        return $query->where('allowed', true);
    }
}
