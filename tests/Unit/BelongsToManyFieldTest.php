<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\BelongsToManyField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\ResourceContext;

beforeEach(function (): void {
    Schema::create('many_field_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('many_field_labels', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('allowed')->default(true);
    });
    Schema::create('many_field_pivot', function (Blueprint $table): void {
        $table->foreignId('record_id');
        $table->foreignId('label_id');
        $table->unique(['record_id', 'label_id']);
    });
});

function manyField(): BelongsToManyField
{
    return BelongsToManyField::make('labels')->relationship('labels', 'name')
        ->query(static fn ($query) => $query->where('allowed', true))->optionsLimit(1);
}

it('saves and clears relations transactionally without filling a JSON attribute', function (): void {
    $label = ManyFieldLabel::query()->create(['name' => 'A']);
    $model = new ManyFieldRecord;
    (new ResourceOperationService)->persist($model, [TextField::make('name'), manyField()], ['name' => 'Record', 'labels' => [(string) $label->getKey()]], ResourceContext::Create);
    expect($model->labels()->count())->toBe(1)
        ->and($model->getAttributes())->not->toHaveKey('labels');
    (new ResourceOperationService)->persist($model, [manyField()], [], ResourceContext::Edit);
    expect($model->labels()->count())->toBe(1);
    (new ResourceOperationService)->persist($model, [manyField()], ['labels' => []], ResourceContext::Edit);
    expect($model->labels()->count())->toBe(0);
});

it('rolls back the parent record when submitted IDs are outside the allowed query', function (): void {
    $label = ManyFieldLabel::query()->create(['name' => 'Forbidden', 'allowed' => false]);
    expect(fn () => (new ResourceOperationService)->persist(new ManyFieldRecord, [TextField::make('name'), manyField()], ['name' => 'Must roll back', 'labels' => [$label->getKey()]], ResourceContext::Create))
        ->toThrow(ValidationException::class);
    expect(ManyFieldRecord::query()->count())->toBe(0);
});

it('retains inaccessible links and selected labels outside the option page', function (): void {
    $first = ManyFieldLabel::query()->create(['name' => 'First']);
    $selected = ManyFieldLabel::query()->create(['name' => 'Selected']);
    $hidden = ManyFieldLabel::query()->create(['name' => 'Hidden', 'allowed' => false]);
    $model = ManyFieldRecord::query()->create(['name' => 'Record']);
    $model->labels()->attach([$selected->getKey(), $hidden->getKey()]);
    expect((new FormStateInitializer)->initialize([manyField()], $model)['labels'])->toBe([(string) $selected->getKey()])
        ->and(manyField()->resolveOptions($model, 'unmatched', [(string) $selected->getKey()]))->toHaveKey($selected->getKey(), 'Selected');
    (new ResourceOperationService)->persist($model, [manyField()], ['labels' => [$first->getKey()]], ResourceContext::Edit);
    expect($model->labels()->pluck('many_field_labels.id')->all())->toEqualCanonicalizing([$first->getKey(), $hidden->getKey()]);
});

it('validates list shape, relation scope, limits and disabled field persistence', function (): void {
    $allowed = ManyFieldLabel::query()->create(['name' => 'Allowed']);
    $hidden = ManyFieldLabel::query()->create(['name' => 'Hidden', 'allowed' => false]);
    $field = manyField()->maxSelected(1);
    foreach ([['bad' => (string) $allowed->getKey()], [(string) $hidden->getKey()], [(string) $allowed->getKey(), (string) $hidden->getKey()]] as $ids) {
        $rules = (new FormValidationRules)->build([$field], ResourceContext::Create, ManyFieldRecord::class, fieldValues: ['labels' => $ids]);
        expect(Validator::make(['fieldValues' => ['labels' => $ids]], $rules)->fails())->toBeTrue();
    }
    $model = ManyFieldRecord::query()->create(['name' => 'Record']);
    (new ResourceOperationService)->persist($model, [manyField()->disabled()], ['labels' => [$allowed->getKey()]], ResourceContext::Edit);
    expect($model->labels()->count())->toBe(0);
});

it('preserves links hidden by related-model global scopes', function (): void {
    $visible = ManyFieldLabel::query()->create(['name' => 'Visible']);
    $hidden = ManyFieldLabel::query()->create(['name' => 'Hidden', 'allowed' => false]);
    $model = ManyFieldRecord::query()->create(['name' => 'Record']);
    $model->labels()->attach([$visible->getKey(), $hidden->getKey()]);
    $field = BelongsToManyField::make('scoped_labels')->relationship('scopedLabels', 'name');
    expect($field->valueForRecord($model))->toBe([(string) $visible->getKey()]);
    $field->sync($model, []);
    expect($model->labels()->pluck('many_field_labels.id')->all())->toBe([$hidden->getKey()]);
});

final class ManyFieldRecord extends Model
{
    protected $table = 'many_field_records';

    protected $guarded = [];

    public $timestamps = false;

    public function scopedLabels(): BelongsToMany
    {
        return $this->belongsToMany(ManyScopedFieldLabel::class, 'many_field_pivot', 'record_id', 'label_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ManyFieldLabel::class, 'many_field_pivot', 'record_id', 'label_id');
    }
}

final class ManyFieldLabel extends Model
{
    protected $table = 'many_field_labels';

    protected $guarded = [];

    public $timestamps = false;
}

final class ManyScopedFieldLabel extends Model
{
    protected $table = 'many_field_labels';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        self::addGlobalScope('visible', static fn (Builder $query) => $query->where('allowed', true));
    }
}
