<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowTransitionResolver;

it('reads current state from workflow state field', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'pending');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->currentState(WorkflowOrderResource::class, $record))
        ->toBe('pending');
});

it('uses initial state when current state is null', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', null);

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->currentState(WorkflowOrderResource::class, $record))
        ->toBe('draft');
});

it('makes transition available for single from state', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;
    $transitions = $resolver->availableTransitions(WorkflowOrderResource::class, $record);

    $names = array_map(static fn (Transition $transition): string => $transition->name(), $transitions);

    expect($names)->toContain('submit');
    expect(in_array('ship', $names, true))->toBeFalse();
});

it('makes transition available for multiple from states', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'pending');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->canRun(WorkflowOrderResource::class, $record, 'cancel'))
        ->toBeTrue();
});

it('does not return transition from other states', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->canRun(WorkflowOrderResource::class, $record, 'ship'))
        ->toBeFalse();
});

it('final state has no transitions unless explicitly allowed', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'cancelled');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->availableTransitions(WorkflowOrderResource::class, $record))
        ->toHaveCount(0)
        ->and($resolver->canRun(WorkflowOrderResource::class, $record, 'reopen'))->toBeFalse()
        ->and($resolver->canRun(WorkflowFinalReopenResource::class, $record, 'reopen'))->toBeTrue();
});

it('unknown transition is not runnable', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->canRun(WorkflowOrderResource::class, $record, 'unknown'))
        ->toBeFalse();
});

it('resource without workflow returns no transitions safely', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->availableTransitions(WorkflowNoFlowResource::class, $record))
        ->toBe([]);
});

it('missing state field falls back safely to initial state', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;

    expect($resolver->currentState(WorkflowCustomStateFieldResource::class, $record))
        ->toBe('queued')
        ->and($resolver->canRun(WorkflowCustomStateFieldResource::class, $record, 'start'))->toBeTrue();
});

it('visibleIf false hides transition', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;
    $names = array_map(
        static fn (Transition $transition): string => $transition->name(),
        $resolver->availableTransitions(WorkflowVisibilityResource::class, $record)
    );

    expect(in_array('submit', $names, true))->toBeFalse();
});

it('visibleIf true shows transition', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');
    $record->setAttribute('number', 'ORD-1');

    $resolver = new WorkflowTransitionResolver;
    $names = array_map(
        static fn (Transition $transition): string => $transition->name(),
        $resolver->availableTransitions(WorkflowVisibilityResource::class, $record)
    );

    expect($names)->toContain('submit');
});

it('authorize false hides transition', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;
    $names = array_map(
        static fn (Transition $transition): string => $transition->name(),
        $resolver->availableTransitions(WorkflowAuthorizationResource::class, $record, 'blocked-user')
    );

    expect(in_array('submit', $names, true))->toBeFalse();
});

it('authorize true shows transition', function (): void {
    $record = new WorkflowRecord;
    $record->setAttribute('status', 'draft');

    $resolver = new WorkflowTransitionResolver;
    $names = array_map(
        static fn (Transition $transition): string => $transition->name(),
        $resolver->availableTransitions(WorkflowAuthorizationResource::class, $record, 'allowed-user')
    );

    expect($names)->toContain('submit');
});

final class WorkflowRecord extends Model
{
    protected $table = 'workflow_records';

    protected $guarded = [];
}

final class WorkflowOrderResource extends Resource
{
    protected static string $model = WorkflowRecord::class;

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
                Transition::make('submit')->from('draft')->to('pending'),
                Transition::make('cancel')->from(['draft', 'pending'])->to('cancelled'),
                Transition::make('ship')->from('pending')->to('shipped'),
            ]);
    }
}

final class WorkflowFinalReopenResource extends Resource
{
    protected static string $model = WorkflowRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('cancelled')->final(),
                State::make('pending'),
            ])
            ->transitions([
                Transition::make('reopen')->from('cancelled')->to('pending'),
            ]);
    }
}

final class WorkflowNoFlowResource extends Resource
{
    protected static string $model = WorkflowRecord::class;
}

final class WorkflowCustomStateFieldResource extends Resource
{
    protected static string $model = WorkflowRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('workflow_status')
            ->initialState('queued')
            ->states([
                State::make('queued'),
                State::make('running'),
            ])
            ->transitions([
                Transition::make('start')->from('queued')->to('running'),
            ]);
    }
}

final class WorkflowVisibilityResource extends Resource
{
    protected static string $model = WorkflowRecord::class;

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
                    ->visibleIf(static fn (WorkflowRecord $record): bool => $record->getAttribute('number') !== null),
            ]);
    }
}

final class WorkflowAuthorizationResource extends Resource
{
    protected static string $model = WorkflowRecord::class;

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
                    ->authorize(static fn (WorkflowRecord $record, mixed $user): bool => $user === 'allowed-user'),
            ]);
    }
}
