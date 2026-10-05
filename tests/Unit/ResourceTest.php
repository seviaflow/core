<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Exceptions\ResourceModelNotConfiguredException;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\ApiResource;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceEvents;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use Tests\Fixtures\Fields\DummyField;

it('returns configured model class', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    expect($resource::model())->toBe(FakeModel::class);
});

it('throws clear exception when model is not configured', function (): void {
    $resource = new class extends Resource {};

    expect(fn () => $resource::model())
        ->toThrow(ResourceModelNotConfiguredException::class, 'must define a non-empty static $model property');
});

it('returns table unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $table = new Table;

    expect($resource::table($table))->toBe($table);
});

it('provides safe default record title metadata and lazy relation contract', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = ResourceMetadataModel::class;
    };
    $record = new ResourceMetadataModel;
    $record->setRawAttributes([
        'id' => 42,
        'order_number' => 'ORD-42',
        'status' => 'confirmed',
        'created_at' => '2026-09-15 08:30:00',
        'updated_at' => '2026-09-15 09:45:00',
    ]);

    expect($resource::recordTitle($record))->toBe('ORD-42')
        ->and($resource::recordMetadata($record))->toBe([
            'Status' => 'confirmed',
            'Created' => '2026-09-15 08:30',
            'Updated' => '2026-09-15 09:45',
        ])
        ->and($resource::lazyRelations())->toBe([]);
});

it('returns form unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $form = new Form;

    expect($resource::form($form))->toBe($form);
});

it('returns api definition unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $api = new ApiResource;

    expect($resource::api($api))->toBe($api)
        ->and($resource::api($api)->isEnabled())->toBeFalse()
        ->and($resource::api($api)->getReadableFields())->toBe([])
        ->and($resource::api($api)->getCreatableFields())->toBe([])
        ->and($resource::api($api)->getUpdatableFields())->toBe([])
        ->and($resource::api($api)->getRequiredOnCreateFields())->toBe([])
        ->and($resource::api($api)->getRequiredOnUpdateFields())->toBe([])
        ->and($resource::api($api)->isFlowEnabled())->toBeFalse()
        ->and($resource::api($api)->isDeletable())->toBeFalse();
});

it('returns export definition unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $export = new ExportResource;

    expect($resource::export($export))->toBe($export)
        ->and($resource::export($export)->isEnabled())->toBeFalse()
        ->and($resource::export($export)->getAllowedFields())->toBe([])
        ->and($resource::export($export)->getFormats())->toBe(['csv']);
});

it('returns import definition unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $import = new ImportResource;

    expect($resource::import($import))->toBe($import)
        ->and($resource::import($import)->isEnabled())->toBeFalse()
        ->and($resource::import($import)->getAllowedFields())->toBe([])
        ->and($resource::import($import)->getRequiredFields())->toBe([]);
});

it('returns events definition unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $events = new ResourceEvents;

    expect($resource::events($events))->toBe($events)
        ->and($resource::events($events)->hasHandlers('creating'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('created'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('updating'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('updated'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('deleting'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('deleted'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('restoring'))->toBeFalse()
        ->and($resource::events($events)->hasHandlers('restored'))->toBeFalse();
});

it('can define events handlers on resource', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function events(ResourceEvents $events): ResourceEvents
        {
            return $events
                ->onCreating(static fn (): null => null)
                ->onCreated(ResourceTestInvokableEventHandler::class)
                ->onUpdating(static fn (): null => null)
                ->onUpdated(static fn (): null => null)
                ->onDeleting(static fn (): null => null)
                ->onDeleted(ResourceTestInvokableEventHandler::class)
                ->onRestoring(static fn (): null => null)
                ->onRestored(static fn (): null => null);
        }
    };

    $events = $resource::events(new ResourceEvents);

    expect($events->hasHandlers('creating'))->toBeTrue()
        ->and($events->hasHandlers('created'))->toBeTrue()
        ->and($events->hasHandlers('updating'))->toBeTrue()
        ->and($events->hasHandlers('updated'))->toBeTrue()
        ->and($events->hasHandlers('deleting'))->toBeTrue()
        ->and($events->hasHandlers('deleted'))->toBeTrue()
        ->and($events->hasHandlers('restoring'))->toBeTrue()
        ->and($events->hasHandlers('restored'))->toBeTrue()
        ->and($events->getHandlers('created'))->toContain(ResourceTestInvokableEventHandler::class)
        ->and($events->getHandlers('deleted'))->toContain(ResourceTestInvokableEventHandler::class);
});

it('can return configured import contract', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function import(ImportResource $import): ImportResource
        {
            return $import
                ->enabled()
                ->allowedFields(['name', 'email', 'status'])
                ->requiredFields(['name', 'email']);
        }
    };

    $import = $resource::import(new ImportResource);

    expect($import->isEnabled())->toBeTrue()
        ->and($import->getAllowedFields())->toBe(['name', 'email', 'status'])
        ->and($import->getRequiredFields())->toBe(['name', 'email']);
});

it('can return configured export contract', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function export(ExportResource $export): ExportResource
        {
            return $export
                ->enabled()
                ->allowedFields([
                    ExportField::make('id')->label('ID'),
                    ExportField::make('email')->label('Email address'),
                ])
                ->formats(['csv']);
        }
    };

    $export = $resource::export(new ExportResource);

    expect($export->isEnabled())->toBeTrue()
        ->and($export->getFormats())->toBe(['csv'])
        ->and($export->getAllowedFields())->toHaveCount(2)
        ->and($export->getAllowedFields()[0]->name())->toBe('id')
        ->and($export->getAllowedFields()[0]->getLabel())->toBe('ID')
        ->and($export->getAllowedFields()[1]->name())->toBe('email')
        ->and($export->getAllowedFields()[1]->getLabel())->toBe('Email address');
});

it('can return configured form', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function form(Form $form): Form
        {
            return $form->fields([
                DummyField::make('name')->label('Name')->required(),
                DummyField::make('email')->label('Email address'),
            ]);
        }
    };

    $fields = $resource::form(new Form)->getFields();

    expect($fields)
        ->toHaveCount(2)
        ->and($fields[0]->name())->toBe('name')
        ->and($fields[0]->getLabel())->toBe('Name')
        ->and($fields[0]->isRequired())->toBeTrue()
        ->and($fields[1]->name())->toBe('email')
        ->and($fields[1]->getLabel())->toBe('Email address');
});

it('returns query hook unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $builder = FakeModelForQueryHook::query();

    expect($resource::query($builder))->toBe($builder)
        ->and($resource::tableQuery($builder))->toBe($builder);
});

it('returns empty actions by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    expect($resource::actions())->toBe([]);
});

it('returns empty bulk actions by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    expect($resource::bulkActions())->toBe([]);
});

it('returns empty relations by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    expect($resource::relations())->toBe([]);
});

it('returns workflow unchanged by default', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;
    };

    $workflow = new Workflow;

    expect($resource::workflow($workflow))->toBe($workflow)
        ->and($resource::workflow($workflow)->getStates())->toBe([]);
});

it('can return configured relations', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function relations(): array
        {
            return [
                HasManyRelation::make('orders')
                    ->label('Orders')
                    ->resource(FakeRelatedResource::class)
                    ->perPage(10),
            ];
        }
    };

    $relations = $resource::relations();

    expect($relations)->toHaveCount(1)
        ->and($relations[0]->getRelationshipName())->toBe('orders')
        ->and($relations[0]->getLabel())->toBe('Orders')
        ->and($relations[0]->getResourceClass())->toBe(FakeRelatedResource::class)
        ->and($relations[0]->getPerPage())->toBe(10);
});

it('can return configured workflow', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function workflow(Workflow $workflow): Workflow
        {
            return $workflow
                ->stateField('status')
                ->initialState('draft')
                ->states([
                    State::make('draft'),
                    State::make('cancelled')->final(),
                ])
                ->transitions([
                    Transition::make('cancel')->from('draft')->to('cancelled')->danger(),
                ]);
        }
    };

    $workflow = $resource::workflow(new Workflow);

    expect($workflow->getStateField())->toBe('status')
        ->and($workflow->getInitialState())->toBe('draft')
        ->and($workflow->getStates())->toHaveCount(2)
        ->and($workflow->getTransitions())->toHaveCount(1)
        ->and($workflow->getTransition('cancel')?->isDanger())->toBeTrue();
});

it('can return configured resource actions', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function actions(): array
        {
            return [
                Action::make('deactivate')
                    ->label('Deactivate account')
                    ->danger()
                    ->requiresConfirmation()
                    ->handle(function (FakeActionRecord $record): void {
                        $record->deactivated = true;
                    }),
            ];
        }
    };

    $actions = $resource::actions();
    $record = new FakeActionRecord;
    $actions[0]->execute($record);

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->name())->toBe('deactivate')
        ->and($actions[0]->isDanger())->toBeTrue()
        ->and($actions[0]->shouldRequireConfirmation())->toBeTrue()
        ->and($record->deactivated)->toBeTrue();
});

it('can return configured resource bulk actions', function (): void {
    $resource = new class extends Resource
    {
        protected static string $model = FakeModel::class;

        public static function bulkActions(): array
        {
            return [
                BulkAction::make('deactivate_selected')
                    ->label('Deactivate selected')
                    ->danger()
                    ->maxRecords(50)
                    ->handle(function (Collection $records): void {
                        $records->each(function (FakeActionRecord $record): void {
                            $record->deactivated = true;
                        });
                    }),
            ];
        }
    };

    $actions = $resource::bulkActions();
    $records = collect([
        new FakeActionRecord,
        new FakeActionRecord,
    ]);

    $actions[0]->execute($records);

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->name())->toBe('deactivate_selected')
        ->and($actions[0]->isDanger())->toBeTrue()
        ->and($actions[0]->shouldRequireConfirmation())->toBeTrue()
        ->and($actions[0]->getMaxRecords())->toBe(50)
        ->and($records->every(fn (FakeActionRecord $record): bool => $record->deactivated))->toBeTrue();
});

final class FakeModel {}

final class ResourceMetadataModel extends Model
{
    protected $table = 'resource_metadata_models';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

final class ResourceTestInvokableEventHandler
{
    public function __invoke(): void {}
}

final class FakeModelForQueryHook extends Model
{
    protected $table = 'fake_models_for_query_hook';

    protected $guarded = [];
}

final class FakeActionRecord
{
    public bool $deactivated = false;
}

final class FakeRelatedResource extends Resource
{
    protected static string $model = FakeModel::class;
}
