<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEvents;

beforeEach(function (): void {
    Schema::create('resource_operation_service_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->decimal('amount', 10, 2)->nullable();
        $table->string('database_default')->nullable()->default('database-default');
        $table->json('tags')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    ResourceOperationServiceCapture::$events = [];
    ResourceOperationServiceCapture::$throwOnCreating = false;
});

it('normalizes shared field values and preserves write-only passwords', function (): void {
    $model = new ResourceOperationServiceRecord;
    $service = new ResourceOperationService;
    $fields = [
        TextField::make('name'),
        ToggleField::make('is_active'),
        MoneyField::make('amount'),
        MultiSelectField::make('tags')->options(['email' => 'Email', 'sms' => 'SMS']),
        PasswordField::make('password'),
    ];

    $changes = $service->applyValues($model, $fields, [
        'name' => '  Alice  ',
        'is_active' => 'false',
        'amount' => '12.50',
        'tags' => [' email ', 'email', 'sms'],
        'password' => 'plain-secret',
    ], ResourceContext::Create);

    expect($changes['name'])->toBe('Alice')
        ->and($changes['is_active'])->toBeFalse()
        ->and($changes['amount'])->toBe('12.50')
        ->and($changes['tags'])->toBe(['email', 'sms'])
        ->and(Hash::check('plain-secret', (string) $model->getAttribute('password')))->toBeTrue();

    $emptyPasswordModel = new ResourceOperationServiceRecord;
    $emptyChanges = $service->applyValues(
        $emptyPasswordModel,
        [PasswordField::make('password')],
        ['password' => ''],
        ResourceContext::Edit
    );

    expect($emptyChanges)->toBe([])
        ->and($emptyPasswordModel->getAttribute('password'))->toBeNull();
});

it('uses the host hashed cast exactly once', function (): void {
    $model = new ResourceOperationServiceRecord;
    $service = new ResourceOperationService;

    $service->applyValues(
        $model,
        [TextField::make('name'), PasswordField::make('password')],
        ['name' => 'Hashed', 'password' => 'cast-secret'],
        ResourceContext::Create
    );
    $model->save();

    expect(Hash::check('cast-secret', (string) $model->fresh()?->getAttribute('password')))->toBeTrue();
});

it('distinguishes omitted values from explicit null and preserves false and zero', function (): void {
    $service = new ResourceOperationService;
    $fields = [
        TextField::make('name'),
        TextField::make('database_default'),
        ToggleField::make('is_active'),
        MoneyField::make('amount'),
    ];

    $omitted = new ResourceOperationServiceRecord;
    $service->applyValues($omitted, $fields, ['name' => 'Defaults'], ResourceContext::Create);
    $omitted->save();

    $cleared = new ResourceOperationServiceRecord;
    $service->applyValues(
        $cleared,
        $fields,
        ['name' => 'Explicit null', 'database_default' => null, 'is_active' => false, 'amount' => '0'],
        ResourceContext::Create
    );
    $cleared->save();

    $updated = ResourceOperationServiceRecord::query()->create([
        'name' => 'Before update',
        'database_default' => 'keep-me',
    ]);
    $service->applyValues($updated, $fields, ['name' => 'After update'], ResourceContext::Edit);
    $updated->save();

    expect($omitted->fresh()?->getAttribute('database_default'))->toBe('database-default')
        ->and($cleared->fresh()?->getAttribute('database_default'))->toBeNull()
        ->and($cleared->fresh()?->getAttribute('is_active'))->toBeFalse()
        ->and($cleared->fresh()?->getAttribute('amount'))->toBe('0.00')
        ->and($updated->fresh()?->getAttribute('database_default'))->toBe('keep-me');
});

it('keeps an explicitly empty imported column distinct from an omitted column', function (): void {
    $service = new ResourceOperationService;
    $fields = [TextField::make('name'), TextField::make('database_default')];
    $prepared = $service->prepareForImport($fields, ['name' => 'Imported', 'database_default' => '']);

    expect(array_key_exists('database_default', $prepared))->toBeTrue()
        ->and($prepared['database_default'])->toBe('');

    $record = new ResourceOperationServiceRecord;
    $service->applyValues($record, $fields, $prepared, ResourceContext::Create);
    $record->save();

    expect($record->fresh()?->getAttribute('database_default'))->toBeNull();
});

it('dispatches before save and after commit with actor source connection and safe changes', function (): void {
    $model = new ResourceOperationServiceRecord;

    (new ResourceOperationService)->execute(
        resourceClass: ResourceOperationServiceResource::class,
        resourceSlug: 'operation-service-records',
        model: $model,
        fields: ResourceOperationServiceResource::form(new Form)->getAllFields(),
        values: [
            'name' => 'Created',
            'password' => 'event-secret',
        ],
        context: ResourceContext::Create,
        operationContext: new ResourceOperationContext(
            actor: 'operator',
            source: ResourceEventContext::SOURCE_API,
            connection: 'testing',
            metadata: ['request_id' => 'req-01'],
            correlationId: 'corr-01',
        ),
    );

    expect(ResourceOperationServiceCapture::$events)->toHaveCount(2)
        ->and(ResourceOperationServiceCapture::$events[0]['event'])->toBe('creating')
        ->and(ResourceOperationServiceCapture::$events[1]['event'])->toBe('created')
        ->and(ResourceOperationServiceCapture::$events[0]['name'])->toBe('Created')
        ->and(ResourceOperationServiceCapture::$events[0]['source'])->toBe('api')
        ->and(ResourceOperationServiceCapture::$events[0]['connection'])->toBe('testing')
        ->and(ResourceOperationServiceCapture::$events[0]['correlation_id'])->toBe('corr-01')
        ->and(ResourceOperationServiceCapture::$events[0]['changes']['password'])->toBe('[REDACTED]')
        ->and(ResourceOperationServiceCapture::$events[1]['result'])->toBe('committed');
});

it('uses the model connection for transactions and lifecycle events', function (): void {
    config()->set('database.connections.secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    Schema::connection('secondary')->create('resource_operation_service_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->decimal('amount', 10, 2)->nullable();
        $table->string('database_default')->nullable()->default('database-default');
        $table->json('tags')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    $model = new ResourceOperationServiceRecord;
    $model->setConnection('secondary');

    (new ResourceOperationService)->execute(
        resourceClass: ResourceOperationServiceResource::class,
        resourceSlug: 'operation-service-records',
        model: $model,
        fields: ResourceOperationServiceResource::form(new Form)->getAllFields(),
        values: ['name' => 'Secondary'],
        context: ResourceContext::Create,
        operationContext: new ResourceOperationContext(source: ResourceEventContext::SOURCE_INLINE_CREATE),
    );

    expect($model->getConnectionName())->toBe('secondary')
        ->and(ResourceOperationServiceRecord::on('secondary')->where('name', 'Secondary')->exists())->toBeTrue()
        ->and(ResourceOperationServiceCapture::$events[0]['connection'])->toBe('secondary')
        ->and(ResourceOperationServiceCapture::$events[1]['connection'])->toBe('secondary');
});

it('rolls back the record and does not dispatch after commit after a failed before event', function (): void {
    ResourceOperationServiceCapture::$throwOnCreating = true;

    expect(fn () => (new ResourceOperationService)->execute(
        resourceClass: ResourceOperationServiceResource::class,
        resourceSlug: 'operation-service-records',
        model: new ResourceOperationServiceRecord,
        fields: ResourceOperationServiceResource::form(new Form)->getAllFields(),
        values: ['name' => 'Rejected'],
        context: ResourceContext::Create,
        operationContext: new ResourceOperationContext(source: ResourceEventContext::SOURCE_IMPORT),
    ))->toThrow(RuntimeException::class, 'Rejected by creating handler');

    expect(ResourceOperationServiceRecord::query()->count())->toBe(0)
        ->and(ResourceOperationServiceCapture::$events)->toHaveCount(1)
        ->and(ResourceOperationServiceCapture::$events[0]['event'])->toBe('creating');
});

final class ResourceOperationServiceRecord extends Model
{
    protected $table = 'resource_operation_service_records';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
        'database_default' => 'string',
        'tags' => 'array',
        'password' => 'hashed',
    ];
}

final class ResourceOperationServiceResource extends Resource
{
    protected static string $model = ResourceOperationServiceRecord::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name'),
            TextField::make('database_default'),
            ToggleField::make('is_active'),
            MoneyField::make('amount'),
            MultiSelectField::make('tags')->options(['email' => 'Email', 'sms' => 'SMS']),
            PasswordField::make('password'),
        ]);
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events
            ->onCreating(static function (Model $record, ResourceEventContext $context): void {
                ResourceOperationServiceCapture::$events[] = [
                    'event' => 'creating',
                    'name' => $record->getAttribute('name'),
                    'source' => $context->source(),
                    'connection' => $context->connection(),
                    'correlation_id' => $context->correlationId(),
                    'changes' => $context->changes(),
                    'result' => $context->metadata()['result'] ?? null,
                ];

                if (ResourceOperationServiceCapture::$throwOnCreating) {
                    throw new RuntimeException('Rejected by creating handler');
                }
            })
            ->onCreated(static function (Model $record, ResourceEventContext $context): void {
                ResourceOperationServiceCapture::$events[] = [
                    'event' => 'created',
                    'name' => $record->getAttribute('name'),
                    'source' => $context->source(),
                    'connection' => $context->connection(),
                    'correlation_id' => $context->correlationId(),
                    'changes' => $context->changes(),
                    'result' => $context->metadata()['result'] ?? null,
                ];
            });
    }
}

final class ResourceOperationServiceCapture
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public static array $events = [];

    public static bool $throwOnCreating = false;
}
