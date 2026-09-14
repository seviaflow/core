<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Exceptions\WorkflowTransitionConflictException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceScopedQuery;
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

        $requestedState = $this->resolver->currentState($resourceClass, $record);

        $transitionedRecord = $record->getConnection()->transaction(function () use (
            $resourceClass,
            $record,
            $transitionName,
            $transition,
            $workflow,
            $requestedState,
            $user,
            $comment,
            $confirmed
        ): Model {
            $lockedRecord = ResourceScopedQuery::make($resourceClass, $record->newQuery())
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRecord instanceof Model) {
                throw new WorkflowTransitionConflictException;
            }

            $currentState = $this->resolver->currentState($resourceClass, $lockedRecord);
            if ($currentState === null) {
                throw new LogicException(sprintf(
                    'Current workflow state could not be resolved for resource [%s].',
                    $resourceClass
                ));
            }

            if ($requestedState !== $currentState) {
                throw new WorkflowTransitionConflictException;
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

            if (! $this->canUpdateRecord($resourceClass, $lockedRecord, $user)) {
                throw new LogicException(sprintf(
                    'You are not authorized to run workflow transition [%s].',
                    $transitionName
                ));
            }

            if (! $transition->isAuthorizedFor($user, $lockedRecord, $context)) {
                throw new LogicException(sprintf(
                    'You are not authorized to run workflow transition [%s].',
                    $transitionName
                ));
            }

            if (! $transition->canRunFor($lockedRecord, $user, $context)) {
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

            $lockedRecord->setAttribute($workflow->getStateField(), $transition->getToState());
            $lockedRecord->save();
            $transition->execute($lockedRecord, $context);
            $this->activityLogger->logFromContext($lockedRecord, $context);

            return $lockedRecord;
        });

        $record->setRawAttributes($transitionedRecord->getAttributes());
        $record->setRelations($transitionedRecord->getRelations());
        $record->syncOriginal();

        return $record;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function canUpdateRecord(string $resourceClass, Model $record, mixed $user): bool
    {
        if ($user !== null && ! $user instanceof Authenticatable) {
            return true;
        }

        return ResourceAuthorization::for($resourceClass)->canUpdate($user, $record);
    }
}
