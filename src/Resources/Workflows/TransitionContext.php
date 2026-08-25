<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Workflows;

final class TransitionContext
{
    public function __construct(
        private readonly mixed $user,
        private readonly string $resourceClass,
        private readonly string $transition,
        private readonly ?string $fromState,
        private readonly string $toState,
        private readonly ?string $comment = null,
        private readonly ?Workflow $workflow = null
    ) {}

    public function user(): mixed
    {
        return $this->user;
    }

    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    public function transition(): string
    {
        return $this->transition;
    }

    public function fromState(): ?string
    {
        return $this->fromState;
    }

    public function toState(): string
    {
        return $this->toState;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function workflow(): ?Workflow
    {
        return $this->workflow;
    }
}
