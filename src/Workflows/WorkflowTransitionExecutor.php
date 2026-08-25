<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\TransitionContext;

final class WorkflowTransitionExecutor
{
    public function __construct(
        private readonly WorkflowTransitionResolver $resolver = new WorkflowTransitionResolver,
        private readonly WorkflowActivityLogger $activityLogger = new WorkflowActivityLogger
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function execute(
        string $resourceClass,
        Model $record,
        string $transitionName,
        mixed $user = null,
        ?string $comment = null,
        bool $confirmed = false
    ): Model {
        $workflow = $this->resolver->resolveWorkflow($resourceClass);

        if ($workflow->getTransitions() === []) {
            throw new LogicException(sprintf(
                'Workflow for resource [%s] does not define any transitions.',
                $resourceClass
            ));
        }

        $transition = $workflow->getTransition($transitionName);

        if ($transition === null) {
            throw new LogicException(sprintf(
                'Workflow transition [%s] is not defined for resource [%s].',
                $transitionName,
                $resourceClass
            ));
        }

        $currentState = $this->resolver->currentState($resourceClass, $record);

        if ($currentState === null) {
            throw new LogicException(sprintf(
                'Current workflow state could not be resolved for resource [%s].',
                $resourceClass
            ));
        }

        if (! in_array($currentState, $transition->getFromStates(), true)) {
            throw new LogicException(sprintf(
                'Workflow transition [%s] cannot run from state [%s].',
                $transitionName,
                $currentState
            ));
        }

        $context = new TransitionContext(
            user: $user,
            resourceClass: $resourceClass,
            transition: $transitionName,
            fromState: $currentState,
            toState: $transition->getToState(),
            comment: $comment,
            workflow: $workflow
        );

        if (! $transition->isAuthorizedFor($user, $record, $context)) {
            throw new LogicException(sprintf(
                'You are not authorized to run workflow transition [%s].',
                $transitionName
            ));
        }

        if (! $transition->canRunFor($record, $user, $context)) {
            throw new LogicException(sprintf(
                'Workflow transition [%s] is blocked by run conditions.',
                $transitionName
            ));
        }

        if ($transition->shouldRequireComment() && trim((string) $comment) === '') {
            throw new InvalidArgumentException(sprintf(
                'Workflow transition [%s] requires a comment.',
                $transitionName
            ));
        }

        if ($transition->shouldRequireConfirmation() && ! $confirmed) {
            throw new InvalidArgumentException(sprintf(
                'Workflow transition [%s] requires explicit confirmation.',
                $transitionName
            ));
        }

        $toState = $transition->getToState();
        $stateField = $workflow->getStateField();
        DB::transaction(function () use ($record, $transition, $context, $stateField, $toState): void {
            $record->setAttribute($stateField, $toState);
            $record->save();
            $transition->execute($record, $context);
            $this->activityLogger->logFromContext($record, $context);
        });

        return $record;
    }
}
