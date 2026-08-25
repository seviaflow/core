<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Workflows\TransitionContext;

final class WorkflowActivityLogger
{
    /**
     * @param  class-string  $resourceClass
     */
    public function log(
        string $resourceClass,
        Model $record,
        string $transitionName,
        ?string $fromState,
        string $toState,
        mixed $user = null,
        ?string $comment = null
    ): WorkflowActivity {
        [$userType, $userId] = $this->resolveUserIdentity($user);

        /** @var WorkflowActivity $activity */
        $activity = WorkflowActivity::query()->create([
            'resource' => $resourceClass,
            'record_type' => $record::class,
            'record_id' => (string) $record->getKey(),
            'transition' => $transitionName,
            'from_state' => $fromState,
            'to_state' => $toState,
            'user_type' => $userType,
            'user_id' => $userId,
            'comment' => $comment,
        ]);

        return $activity;
    }

    public function logFromContext(Model $record, TransitionContext $context): WorkflowActivity
    {
        return $this->log(
            resourceClass: $context->resourceClass(),
            record: $record,
            transitionName: $context->transition(),
            fromState: $context->fromState(),
            toState: $context->toState(),
            user: $context->user(),
            comment: $context->comment()
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveUserIdentity(mixed $user): array
    {
        if ($user instanceof Model) {
            return [$user::class, (string) $user->getKey()];
        }

        return [null, null];
    }
}
