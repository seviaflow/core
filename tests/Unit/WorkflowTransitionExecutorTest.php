<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Exceptions\WorkflowTransitionConflictException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\TransitionContext;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowActivity;
use SeviaFlow\Core\Workflows\WorkflowTransitionExecutor;

beforeEach(function (): void {
    if (! Schema::hasTable('workflow_executor_records')) {
        Schema::create('workflow_executor_records', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('seviaflow_workflow_activities')) {
        Schema::create('seviaflow_workflow_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('record_type');
            $table->string('record_id');
            $table->string('transition');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    DB::table('workflow_executor_records')->delete();
    DB::table('users')->delete();
    DB::table('seviaflow_workflow_activities')->delete();
});

it('valid transition changes state', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'submit'
    );

    $record->refresh();

    expect($record->status)->toBe('pending')
        ->and(WorkflowActivity::query()->count())->toBe(1);
});

it('invalid transition from wrong state is rejected', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'shipped']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'submit'
    ))->toThrow(LogicException::class, 'cannot run from state');
});

it('rejects a stale transition after the record state changes', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);
    $staleRecord = WorkflowExecutorRecord::query()->findOrFail($record->getKey());

    DB::table('workflow_executor_records')
        ->where('id', $record->getKey())
        ->update(['status' => 'pending']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $staleRecord,
        'submit'
    ))->toThrow(
        WorkflowTransitionConflictException::class,
        'record changed'
    );

    expect($record->fresh()?->status)->toBe('pending')
        ->and(WorkflowActivity::query()->count())->toBe(0);
});

it('unknown transition is rejected', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'unknown'
    ))->toThrow(LogicException::class, 'is not defined');
});

it('required comment is enforced', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'pending']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'cancel',
        comment: ''
    ))->toThrow(InvalidArgumentException::class, 'requires a comment');
});

it('required confirmation is enforced', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'pending']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'cancel',
        comment: 'customer requested',
        confirmed: false
    ))->toThrow(InvalidArgumentException::class, 'requires explicit confirmation');
});

it('handler is executed', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'submit'
    );

    $record->refresh();

    expect($record->note)->toBe('submitted');
});

it('handler exception rolls back state change', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorFailingHandlerResource::class,
        $record,
        'submit'
    ))->toThrow(RuntimeException::class, 'handler failed');

    $record->refresh();
    expect($record->status)->toBe('draft')
        ->and(WorkflowActivity::query()->count())->toBe(0);
});

it('transition context contains user comment from and to states', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'pending']);
    $capture = new WorkflowContextCapture;
    app()->instance(WorkflowContextCapture::class, $capture);
    $capture->context = null;

    $user = WorkflowExecutorUser::query()->create(['name' => 'Op']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorContextResource::class,
        $record,
        'cancel',
        user: $user,
        comment: 'Customer asked',
        confirmed: true
    );

    /** @var TransitionContext $transitionContext */
    $transitionContext = $capture->context;

    expect($transitionContext)->toBeInstanceOf(TransitionContext::class)
        ->and($transitionContext->user())->toBe($user)
        ->and($transitionContext->comment())->toBe('Customer asked')
        ->and($transitionContext->fromState())->toBe('pending')
        ->and($transitionContext->toState())->toBe('cancelled')
        ->and($transitionContext->transition())->toBe('cancel');
});

it('transition without handler still changes state', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorNoHandlerResource::class,
        $record,
        'submit'
    );

    $record->refresh();
    expect($record->status)->toBe('pending');
});

it('missing workflow fails safely', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorNoWorkflowResource::class,
        $record,
        'submit'
    ))->toThrow(LogicException::class, 'does not define any transitions');
});

it('canRunIf false blocks execution', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorConditionalResource::class,
        $record,
        'submit'
    ))->toThrow(LogicException::class, 'blocked by run conditions');

    expect($record->fresh()?->status)->toBe('draft');
});

it('canRunIf true allows execution', function (): void {
    $record = WorkflowExecutorRecord::query()->create([
        'status' => 'draft',
        'note' => 'allow',
    ]);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorConditionalResource::class,
        $record,
        'submit'
    );

    expect($record->fresh()?->status)->toBe('pending');
});

it('authorize false blocks execution', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    expect(fn () => (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorAuthorizedResource::class,
        $record,
        'submit',
        user: 'blocked-user'
    ))->toThrow(LogicException::class, 'not authorized');

    expect($record->fresh()?->status)->toBe('draft');
});

it('authorize true allows execution', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorAuthorizedResource::class,
        $record,
        'submit',
        user: 'allowed-user'
    );

    expect($record->fresh()?->status)->toBe('pending');
});

it('activity stores resource record transition and states', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorResource::class,
        $record,
        'submit'
    );

    $activity = WorkflowActivity::query()->firstOrFail();

    expect($activity->resource)->toBe(WorkflowExecutorResource::class)
        ->and($activity->record_type)->toBe(WorkflowExecutorRecord::class)
        ->and($activity->record_id)->toBe((string) $record->getKey())
        ->and($activity->transition)->toBe('submit')
        ->and($activity->from_state)->toBe('draft')
        ->and($activity->to_state)->toBe('pending');
});

it('activity stores user identity when provided', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'pending']);
    $user = WorkflowExecutorUser::query()->create(['name' => 'Manager']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorContextResource::class,
        $record,
        'cancel',
        user: $user,
        comment: 'Requested by customer',
        confirmed: true
    );

    $activity = WorkflowActivity::query()->firstOrFail();

    expect($activity->user_type)->toBe(WorkflowExecutorUser::class)
        ->and($activity->user_id)->toBe((string) $user->getKey());
});

it('activity stores comment', function (): void {
    $record = WorkflowExecutorRecord::query()->create(['status' => 'pending']);

    (new WorkflowTransitionExecutor)->execute(
        WorkflowExecutorContextResource::class,
        $record,
        'cancel',
        comment: 'Customer changed mind',
        confirmed: true
    );

    $activity = WorkflowActivity::query()->firstOrFail();

    expect($activity->comment)->toBe('Customer changed mind');
});

it('activities can be queried for a record', function (): void {
    $recordA = WorkflowExecutorRecord::query()->create(['status' => 'draft']);
    $recordB = WorkflowExecutorRecord::query()->create(['status' => 'draft']);

    $executor = new WorkflowTransitionExecutor;
    $executor->execute(WorkflowExecutorResource::class, $recordA, 'submit');
    $executor->execute(WorkflowExecutorResource::class, $recordB, 'submit');

    $activitiesForA = WorkflowActivity::query()
        ->forRecord($recordA)
        ->get();

    expect($activitiesForA)->toHaveCount(1)
        ->and($activitiesForA->first()?->record_id)->toBe((string) $recordA->getKey());
});

/**
 * @property string $status
 * @property string|null $note
 */
final class WorkflowExecutorRecord extends Model
{
    protected $table = 'workflow_executor_records';

    protected $guarded = [];
}

final class WorkflowExecutorUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}

final class WorkflowExecutorResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('pending'),
                State::make('shipped'),
                State::make('cancelled')->final(),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->handle(static function (WorkflowExecutorRecord $record, TransitionContext $context): void {
                        $record->note = 'submitted';
                        $record->save();
                    }),
                Transition::make('cancel')
                    ->from(['draft', 'pending'])
                    ->to('cancelled')
                    ->requiresComment()
                    ->requiresConfirmation(),
            ]);
    }
}

final class WorkflowExecutorFailingHandlerResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('pending'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->handle(static function (): void {
                        throw new RuntimeException('handler failed');
                    }),
            ]);
    }
}

final class WorkflowContextCapture
{
    public ?TransitionContext $context = null;

    public function __invoke(WorkflowExecutorRecord $record, TransitionContext $context): void
    {
        $this->context = $context;
    }
}

final class WorkflowExecutorContextResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('pending'),
                State::make('cancelled')->final(),
            ])
            ->transitions([
                Transition::make('cancel')
                    ->from('pending')
                    ->to('cancelled')
                    ->requiresComment()
                    ->requiresConfirmation()
                    ->handle(WorkflowContextCapture::class),
            ]);
    }
}

final class WorkflowExecutorNoHandlerResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('pending'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending'),
            ]);
    }
}

final class WorkflowExecutorNoWorkflowResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;
}

final class WorkflowExecutorConditionalResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('pending'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->canRunIf(static fn (WorkflowExecutorRecord $record): bool => (string) $record->note === 'allow'),
            ]);
    }
}

final class WorkflowExecutorAuthorizedResource extends Resource
{
    protected static string $model = WorkflowExecutorRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft'),
                State::make('pending'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->authorize(static fn (WorkflowExecutorRecord $record, mixed $user): bool => $user === 'allowed-user'),
            ]);
    }
}
