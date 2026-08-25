<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Tables\ResourceTableQuery;

beforeEach(function (): void {
    config()->set('seviaflow.performance.pagination.max_per_page', 5);
    config()->set('seviaflow.performance.search.mode', 'contains');

    Schema::create('resource_table_query_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->foreignId('company_id')->nullable();
        $table->string('status')->default('draft');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    Schema::create('resource_table_query_companies', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('resource_table_query_custom_key_users', function (Blueprint $table): void {
        $table->string('uuid')->primary();
        $table->string('name');
        $table->string('email');
        $table->string('status')->default('draft');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
});

it('builds base query from resource model', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Base User',
        'email' => 'base@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $query = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table));

    expect($query->baseQuery()->count())->toBe(1);
});

it('applies search only on searchable columns', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Alice Smith',
        'email' => 'hidden-match@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Bob Jones',
        'email' => 'bob@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);

    $query = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->search('Alice');

    expect($query->query()->pluck('name')->all())->toBe(['Alice Smith']);

    $nonSearchableQuery = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->search('hidden-match@example.com');

    expect($nonSearchableQuery->query()->pluck('name')->all())->toBe([]);
});

it('supports contains search mode', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Ann Marie',
        'email' => 'ann@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Marie Curie',
        'email' => 'marie@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(ContainsSearchResource::class, ContainsSearchResource::table(new Table))
        ->search('rie')
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toContain('Ann Marie')
        ->toContain('Marie Curie');
});

it('supports starts_with search mode', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Alpha One',
        'email' => 'alpha@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'One Alpha',
        'email' => 'one@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(StartsWithSearchResource::class, StartsWithSearchResource::table(new Table))
        ->search('Alpha')
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha One']);
});

it('supports exact search mode', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Exact Match',
        'email' => 'exact@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Exact Match Extended',
        'email' => 'exact2@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(ExactSearchResource::class, ExactSearchResource::table(new Table))
        ->search('Exact Match')
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Exact Match']);
});

it('uses performance.search.default_mode when column mode is not specified', function (): void {
    config()->set('seviaflow.performance.search.mode', null);
    config()->set('seviaflow.performance.search.default_mode', 'starts_with');

    ResourceTableQueryUser::query()->create([
        'name' => 'Alpha One',
        'email' => 'alpha@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'One Alpha',
        'email' => 'one@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->search('Alpha')
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha One']);
});

it('search uses parameter bindings', function (): void {
    $query = ResourceTableQuery::make(ContainsSearchResource::class, ContainsSearchResource::table(new Table))
        ->search('needle')
        ->query();

    expect($query->toSql())->toContain('like ?')
        ->and($query->getBindings())->toContain('%needle%');
});

it('applies sorting only for sortable columns', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Charlie',
        'email' => 'charlie@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);

    $sorted = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->sort('name', 'desc')
        ->query()
        ->pluck('name')
        ->all();

    expect($sorted)->toBe(['Charlie', 'Alice']);

    $unsorted = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->sort('email', 'desc')
        ->query()
        ->pluck('name')
        ->all();

    expect($unsorted)->toContain('Charlie')->toContain('Alice');
});

it('applies configured filters safely', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Active Draft',
        'email' => 'active-draft@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Inactive Published',
        'email' => 'inactive-published@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);

    $filtered = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->filters([
            'is_active' => '1',
            'status' => 'draft',
        ])
        ->query()
        ->pluck('name')
        ->all();

    expect($filtered)->toBe(['Active Draft']);

    $invalidSelectValue = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->filters(['status' => 'unknown'])
        ->query()
        ->pluck('name')
        ->all();

    expect($invalidSelectValue)->toContain('Active Draft')->toContain('Inactive Published');
});

it('paginates query results', function (): void {
    foreach (range(1, 8) as $index) {
        ResourceTableQueryUser::query()->create([
            'name' => 'User '.$index,
            'email' => 'user'.$index.'@example.com',
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    $paginator = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->sort('name', 'asc')
        ->paginate(15);

    expect($paginator->items())->toHaveCount(5)
        ->and($paginator->total())->toBe(8);
});

it('respects configured pagination mode', function (): void {
    foreach (range(1, 6) as $index) {
        ResourceTableQueryUser::query()->create([
            'name' => 'Mode User '.$index,
            'email' => 'mode-user'.$index.'@example.com',
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    config()->set('seviaflow.performance.pagination.mode', 'simple');

    $simplePaginator = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->paginate(2);

    expect($simplePaginator)->toBeInstanceOf(Paginator::class);

    config()->set('seviaflow.performance.pagination.mode', 'length_aware');

    $lengthAwarePaginator = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->paginate(2);

    expect($lengthAwarePaginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($lengthAwarePaginator->total())->toBe(6);
});

it('selects only required table columns instead of wildcard', function (): void {
    $query = ResourceTableQuery::make(ResourceTableQueryResource::class, ResourceTableQueryResource::table(new Table))
        ->sort('name', 'asc')
        ->filters(['status' => 'draft'])
        ->query();

    $selectedColumns = $query->getQuery()->columns ?? [];

    expect($selectedColumns)->not->toBeNull()
        ->and($selectedColumns)->not->toContain('*')
        ->and($selectedColumns)->toContain('id')
        ->and($selectedColumns)->toContain('name')
        ->and($selectedColumns)->toContain('email')
        ->and($selectedColumns)->toContain('status')
        ->and($selectedColumns)->toContain('is_active');
});

it('does not select hidden index columns unless required for sort or filter', function (): void {
    $query = ResourceTableQuery::make(HiddenColumnSelectResource::class, HiddenColumnSelectResource::table(new Table))
        ->query();

    $selectedColumns = $query->getQuery()->columns ?? [];

    expect($selectedColumns)->toContain('id')
        ->and($selectedColumns)->toContain('name')
        ->and($selectedColumns)->not->toContain('email');
});

it('still selects hidden column when required for filters', function (): void {
    $query = ResourceTableQuery::make(HiddenColumnFilterResource::class, HiddenColumnFilterResource::table(new Table))
        ->filters(['email' => 'anything@example.com'])
        ->query();

    $selectedColumns = $query->getQuery()->columns ?? [];

    expect($selectedColumns)->toContain('email');
});

it('always includes custom model primary key in selected columns', function (): void {
    $query = ResourceTableQuery::make(CustomKeyResourceTableQueryResource::class, CustomKeyResourceTableQueryResource::table(new Table))
        ->query();

    $selectedColumns = $query->getQuery()->columns ?? [];

    expect($selectedColumns)->toContain('uuid')
        ->and($selectedColumns)->not->toContain('*');
});

it('allows disabling select-only-visible-columns optimization', function (): void {
    $table = ResourceTableQueryResource::table(new Table)->selectOnlyVisibleColumns(false);

    $query = ResourceTableQuery::make(ResourceTableQueryResource::class, $table)->query();

    expect($query->getQuery()->columns)->toBeNull();
});

it('applies resource query hook before search filter and sort', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Published Alpha',
        'email' => 'published-alpha@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Draft Alpha',
        'email' => 'draft-alpha@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    config()->set('seviaflow.performance.search.default_mode', 'contains');

    $query = ResourceTableQuery::make(HookedResourceTableQueryResource::class, HookedResourceTableQueryResource::table(new Table))
        ->search('Alpha')
        ->filters(['is_active' => '1'])
        ->sort('name', 'asc')
        ->query();

    expect($query->pluck('name')->all())->toBe(['Published Alpha']);
});

it('applies filters after resource query hook', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Published Active',
        'email' => 'published-active@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Published Inactive',
        'email' => 'published-inactive@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Draft Active',
        'email' => 'draft-active@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(HookedResourceTableQueryResource::class, HookedResourceTableQueryResource::table(new Table))
        ->filters(['is_active' => '1'])
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Published Active']);
});

it('applies sorting after resource query hook', function (): void {
    ResourceTableQueryUser::query()->create([
        'name' => 'Zulu',
        'email' => 'zulu@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Alpha',
        'email' => 'alpha@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);
    ResourceTableQueryUser::query()->create([
        'name' => 'Beta Draft',
        'email' => 'beta-draft@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $names = ResourceTableQuery::make(HookedResourceTableQueryResource::class, HookedResourceTableQueryResource::table(new Table))
        ->sort('name', 'asc')
        ->query()
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Zulu']);
});

it('applies pagination after resource query hook', function (): void {
    config()->set('seviaflow.performance.pagination.max_per_page', 2);

    foreach (['P1', 'P2', 'P3'] as $name) {
        ResourceTableQueryUser::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'status' => 'published',
            'is_active' => true,
        ]);
    }
    ResourceTableQueryUser::query()->create([
        'name' => 'Draft Out',
        'email' => 'draft-out@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $paginator = ResourceTableQuery::make(HookedResourceTableQueryResource::class, HookedResourceTableQueryResource::table(new Table))
        ->sort('name', 'asc')
        ->paginate(10);

    $names = array_map(
        static fn (ResourceTableQueryUser $user): string => $user->name,
        $paginator->items()
    );

    expect($paginator->total())->toBe(3)
        ->and($names)->toBe(['P1', 'P2']);
});

it('eager loads belongsTo relation for belongsTo column', function (): void {
    $company = ResourceTableQueryCompany::query()->create(['name' => 'Acme']);

    ResourceTableQueryUser::query()->create([
        'name' => 'Has Company',
        'email' => 'has-company@example.com',
        'company_id' => $company->getKey(),
        'status' => 'draft',
        'is_active' => true,
    ]);

    $record = ResourceTableQuery::make(BelongsToResourceTableQueryResource::class, BelongsToResourceTableQueryResource::table(new Table))
        ->query()
        ->first();

    expect($record)->not->toBeNull()
        ->and($record?->relationLoaded('company'))->toBeTrue();
});

it('includes parent foreign key in select optimization for belongsTo column', function (): void {
    $query = ResourceTableQuery::make(BelongsToResourceTableQueryResource::class, BelongsToResourceTableQueryResource::table(new Table))
        ->query();

    $selectedColumns = $query->getQuery()->columns ?? [];

    expect($selectedColumns)->toContain('company_id');
});

it('does not produce n plus one company queries for belongsTo column listing', function (): void {
    $companies = collect(range(1, 3))->map(fn (int $i): ResourceTableQueryCompany => ResourceTableQueryCompany::query()->create([
        'name' => 'Company '.$i,
    ]));

    foreach (range(1, 10) as $index) {
        ResourceTableQueryUser::query()->create([
            'name' => 'User '.$index,
            'email' => 'user'.$index.'@example.com',
            'company_id' => $companies[$index % $companies->count()]->getKey(),
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $records = ResourceTableQuery::make(BelongsToResourceTableQueryResource::class, BelongsToResourceTableQueryResource::table(new Table))
        ->paginate(10)
        ->items();

    foreach ($records as $record) {
        expect($record?->company?->name)->not->toBeNull();
    }

    $companySelectQueries = collect(DB::getQueryLog())
        ->filter(static fn (array $entry): bool => str_contains(strtolower((string) ($entry['query'] ?? '')), 'from "resource_table_query_companies"'))
        ->count();

    expect($companySelectQueries)->toBe(1);
});

final class ResourceTableQueryResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email'),
                TextColumn::make('status'),
                TextColumn::make('is_active'),
            ])
            ->filters([
                BooleanFilter::make('is_active'),
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),
            ]);
    }
}

/**
 * @property string $name
 * @property string $email
 * @property int|null $company_id
 * @property string $status
 * @property bool $is_active
 */
final class ResourceTableQueryUser extends Model
{
    protected $table = 'resource_table_query_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(ResourceTableQueryCompany::class, 'company_id');
    }
}

final class CustomKeyResourceTableQueryResource extends Resource
{
    protected static string $model = ResourceTableQueryCustomKeyUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email'),
        ]);
    }
}

final class ResourceTableQueryCustomKeyUser extends Model
{
    protected $table = 'resource_table_query_custom_key_users';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

final class HookedResourceTableQueryResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->sortable(),
                TextColumn::make('is_active'),
            ])
            ->filters([
                BooleanFilter::make('is_active'),
            ]);
    }

    public static function query(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}

final class ContainsSearchResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(mode: 'contains'),
        ]);
    }
}

final class StartsWithSearchResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(mode: 'starts_with'),
        ]);
    }
}

final class ExactSearchResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(mode: 'exact'),
        ]);
    }
}

final class HiddenColumnSelectResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('email')->hiddenOnIndex(),
        ]);
    }
}

final class HiddenColumnFilterResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email')->hiddenOnIndex(),
            ])
            ->filters([
                SelectFilter::make('email')->options([
                    'anything@example.com' => 'Anything',
                ]),
            ]);
    }
}

final class BelongsToResourceTableQueryResource extends Resource
{
    protected static string $model = ResourceTableQueryUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            BelongsToColumn::make('company')->relationship('company', 'name'),
        ]);
    }
}

/**
 * @property string $name
 */
final class ResourceTableQueryCompany extends Model
{
    protected $table = 'resource_table_query_companies';

    protected $guarded = [];
}
