<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\BelongsToFieldOptions;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Resource;

beforeEach(function (): void {
    Schema::create('belongs_to_options_companies', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->boolean('active')->default(true);
        $table->unsignedBigInteger('tenant_id')->default(1);
        $table->timestamps();
    });

    Schema::create('belongs_to_options_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });
});

it('applies query callback when resolving options', function (): void {
    BelongsToOptionsCompany::query()->create(['name' => 'Active A', 'active' => true, 'tenant_id' => 1]);
    BelongsToOptionsCompany::query()->create(['name' => 'Inactive B', 'active' => false, 'tenant_id' => 1]);

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name')
        ->query(static fn ($query) => $query->where('active', true));

    $options = (new BelongsToFieldOptions)->resolve([$field], BelongsToOptionsUser::class);

    expect(array_values($options['company_id']))->toBe(['Active A']);
});

it('enforces options limit while resolving options', function (): void {
    foreach (range(1, 5) as $index) {
        BelongsToOptionsCompany::query()->create([
            'name' => 'Company '.$index,
            'active' => true,
            'tenant_id' => 1,
        ]);
    }

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name')
        ->optionsLimit(2);

    $options = (new BelongsToFieldOptions)->resolve([$field], BelongsToOptionsUser::class);

    expect($options['company_id'])->toHaveCount(2);
});

it('searches only configured searchable option columns', function (): void {
    BelongsToOptionsCompany::query()->create(['name' => 'Alpha', 'email' => 'alpha@example.com', 'active' => true, 'tenant_id' => 1]);
    BelongsToOptionsCompany::query()->create(['name' => 'Beta', 'email' => 'beta@example.com', 'active' => true, 'tenant_id' => 1]);

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name')
        ->searchableOptions(['name']);

    $options = (new BelongsToFieldOptions)->resolve(
        [$field],
        BelongsToOptionsUser::class,
        ['company_id' => 'Alpha']
    );

    expect(array_values($options['company_id']))->toBe(['Alpha']);
});

it('does not search when no searchable option columns are configured', function (): void {
    BelongsToOptionsCompany::query()->create(['name' => 'Alpha', 'email' => 'alpha@example.com', 'active' => true, 'tenant_id' => 1]);
    BelongsToOptionsCompany::query()->create(['name' => 'Beta', 'email' => 'beta@example.com', 'active' => true, 'tenant_id' => 1]);

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name');

    $options = (new BelongsToFieldOptions)->resolve(
        [$field],
        BelongsToOptionsUser::class,
        ['company_id' => 'does-not-match']
    );

    expect($options['company_id'])->toHaveCount(2);
});

it('throws clear error when query callback returns invalid value', function (): void {
    BelongsToOptionsCompany::query()->create(['name' => 'Alpha', 'active' => true, 'tenant_id' => 1]);

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name')
        ->query(static fn () => 'invalid');

    expect(fn () => (new BelongsToFieldOptions)->resolve([$field], BelongsToOptionsUser::class))
        ->toThrow(InvalidArgumentException::class, 'BelongsToField query callback');
});

it('applies the related resource scope to options and validation', function (): void {
    $allowed = BelongsToOptionsCompany::query()->create([
        'name' => 'Allowed',
        'active' => true,
        'tenant_id' => 2,
    ]);
    $forbidden = BelongsToOptionsCompany::query()->create([
        'name' => 'Forbidden',
        'active' => true,
        'tenant_id' => 1,
    ]);

    $field = BelongsToField::make('company_id')
        ->relationship('company', 'name')
        ->resource(BelongsToOptionsCompanyResource::class);

    $options = (new BelongsToFieldOptions)->resolve([$field], BelongsToOptionsUser::class);
    $rules = $field->defaultValidationRules(modelClass: BelongsToOptionsUser::class);

    expect($options['company_id'])->toBe([(string) $allowed->getKey() => 'Allowed'])
        ->and(Validator::make(['company_id' => $allowed->getKey()], ['company_id' => $rules])->passes())
        ->toBeTrue()
        ->and(Validator::make(['company_id' => $forbidden->getKey()], ['company_id' => $rules])->fails())
        ->toBeTrue();
});

final class BelongsToOptionsUser extends Model
{
    protected $table = 'belongs_to_options_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToOptionsCompany::class, 'company_id');
    }
}

final class BelongsToOptionsCompany extends Model
{
    protected $table = 'belongs_to_options_companies';

    protected $guarded = [];
}

final class BelongsToOptionsCompanyResource extends Resource
{
    protected static string $model = BelongsToOptionsCompany::class;

    public static function query(Builder $query): Builder
    {
        return $query->where('tenant_id', 2);
    }
}
