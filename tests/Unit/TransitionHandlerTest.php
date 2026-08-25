<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\TransitionContext;
use SeviaFlow\Core\Resources\Workflows\Workflow;

it('stores callback handler', function (): void {
    $handler = static fn (object $record, TransitionContext $context): bool => true;

    $transition = Transition::make('submit')->handle($handler);

    expect($transition->getHandler())->toBe($handler);
});

it('stores invokable class handler', function (): void {
    $transition = Transition::make('submit')->handle(TestTransitionHandler::class);

    expect($transition->getHandler())->toBe(TestTransitionHandler::class);
});

it('callback handler receives record and context', function (): void {
    $record = new stdClass;
    $record->processed = false;

    $context = new TransitionContext(
        user: 'operator-1',
        resourceClass: TestWorkflowResource::class,
        transition: 'submit',
        fromState: 'draft',
        toState: 'pending',
        comment: 'Looks good',
        workflow: new Workflow
    );

    Transition::make('submit')
        ->handle(function (object $row, TransitionContext $ctx) use ($record): string {
            $row->processed = true;

            expect($row)->toBe($record)
                ->and($ctx->transition())->toBe('submit')
                ->and($ctx->fromState())->toBe('draft')
                ->and($ctx->toState())->toBe('pending');

            return 'ok';
        })
        ->execute($record, $context);

    expect($record->processed)->toBeTrue();
});

it('invokable class handler receives record and context', function (): void {
    $record = new stdClass;
    $context = new TransitionContext(
        user: null,
        resourceClass: TestWorkflowResource::class,
        transition: 'ship',
        fromState: 'paid',
        toState: 'shipped'
    );

    $handler = new TestTransitionHandler;
    app()->instance(TestTransitionHandler::class, $handler);

    $result = Transition::make('ship')
        ->handle(TestTransitionHandler::class)
        ->execute($record, $context);

    expect($result)->toBe('handled')
        ->and($handler->receivedRecord)->toBe($record)
        ->and($handler->receivedContext)->toBe($context);
});

it('transition without handler executes safely as no-op', function (): void {
    $transition = Transition::make('cancel');
    $context = new TransitionContext(
        user: null,
        resourceClass: TestWorkflowResource::class,
        transition: 'cancel',
        fromState: 'draft',
        toState: 'cancelled'
    );

    expect($transition->execute(new stdClass, $context))->toBeNull();
});

it('transition context exposes expected values', function (): void {
    $workflow = new Workflow;
    $context = new TransitionContext(
        user: 'operator-1',
        resourceClass: TestWorkflowResource::class,
        transition: 'approve',
        fromState: 'pending',
        toState: 'approved',
        comment: 'Approved by supervisor',
        workflow: $workflow
    );

    expect($context->user())->toBe('operator-1')
        ->and($context->resourceClass())->toBe(TestWorkflowResource::class)
        ->and($context->transition())->toBe('approve')
        ->and($context->fromState())->toBe('pending')
        ->and($context->toState())->toBe('approved')
        ->and($context->comment())->toBe('Approved by supervisor')
        ->and($context->workflow())->toBe($workflow);
});

it('handler exception bubbles predictably', function (): void {
    $context = new TransitionContext(
        user: null,
        resourceClass: TestWorkflowResource::class,
        transition: 'submit',
        fromState: 'draft',
        toState: 'pending'
    );

    $transition = Transition::make('submit')
        ->handle(static function (): void {
            throw new RuntimeException('handler failed');
        });

    expect(fn () => $transition->execute(new stdClass, $context))
        ->toThrow(RuntimeException::class, 'handler failed');
});

it('rejects empty handler class name', function (): void {
    expect(fn () => Transition::make('submit')->handle('   '))
        ->toThrow(InvalidArgumentException::class, 'handler class cannot be empty');
});

it('throws clear exception when resolved class is not invokable', function (): void {
    app()->instance(NonInvokableTransitionHandler::class, new NonInvokableTransitionHandler);

    $transition = Transition::make('submit')->handle(NonInvokableTransitionHandler::class);
    $context = new TransitionContext(
        user: null,
        resourceClass: TestWorkflowResource::class,
        transition: 'submit',
        fromState: 'draft',
        toState: 'pending'
    );

    expect(fn () => $transition->execute(new stdClass, $context))
        ->toThrow(LogicException::class, 'must be invokable');
});

final class TestWorkflowResource {}

final class TestTransitionHandler
{
    public mixed $receivedRecord = null;

    public ?TransitionContext $receivedContext = null;

    public function __invoke(mixed $record, TransitionContext $context): string
    {
        $this->receivedRecord = $record;
        $this->receivedContext = $context;

        return 'handled';
    }
}

final class NonInvokableTransitionHandler
{
    public function process(): void {}
}
