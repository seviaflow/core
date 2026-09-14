<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Exceptions\WorkflowStateMutationException;
use SeviaFlow\Core\Forms\FormPersistence;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;

beforeEach(function (): void {
    Schema::create('workflow_integrity_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

it('initializes workflow state through the shared operation service', function (): void {
    $record = (new ResourceOperationService)->execute(
        resourceClass: WorkflowIntegrityResource::class,
        resourceSlug: 'workflow-integrity',
        model: new WorkflowIntegrityRecord,
        fields: WorkflowIntegrityResource::form(new Form)->getAllFields(),
        values: ['name' => 'Created safely'],
        context: ResourceContext::Create,
        operationContext: new ResourceOperationContext(
            source: ResourceEventContext::SOURCE_API
        ),
    );

    expect($record->getAttribute('status'))->toBe('draft');
});

it('rejects workflow state writes before ordinary persistence', function (): void {
    $record = WorkflowIntegrityRecord::query()->create([
        'name' => 'Existing',
        'status' => 'draft',
    ]);

    expect(fn () => (new ResourceOperationService)->execute(
        resourceClass: WorkflowIntegrityResource::class,
        resourceSlug: 'workflow-integrity',
        model: $record,
        fields: WorkflowIntegrityResource::form(new Form)->getAllFields(),
        values: ['status' => 'approved'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(
            source: ResourceEventContext::SOURCE_RELATION
        ),
    ))->toThrow(WorkflowStateMutationException::class, 'workflow-controlled');

    expect($record->fresh()?->status)->toBe('draft');
});

it('protects the legacy form persistence entry point for registered resources', function (): void {
    app()->instance(ResourceRegistry::class, new ResourceRegistry([
        'workflow-integrity' => WorkflowIntegrityResource::class,
    ]));
    $record = WorkflowIntegrityRecord::query()->create([
        'name' => 'Existing',
        'status' => 'draft',
    ]);

    expect(fn () => (new FormPersistence)->persist(
        model: $record,
        fields: [TextField::make('name')],
        validatedFieldValues: ['status' => 'approved'],
        context: ResourceContext::Edit,
    ))->toThrow(WorkflowStateMutationException::class, 'workflow-controlled');

    expect($record->fresh()?->status)->toBe('draft');
});

/**
 * @property string|null $status
 */
final class WorkflowIntegrityRecord extends Model
{
    protected $table = 'workflow_integrity_records';

    protected $guarded = [];
}

final class WorkflowIntegrityResource extends Resource
{
    protected static string $model = WorkflowIntegrityRecord::class;

    public static function form(Form $form): Form
    {
        return $form->fields([TextField::make('name')]);
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('approved'),
            ])
            ->transitions([
                Transition::make('approve')->from('draft')->to('approved'),
            ]);
    }
}
