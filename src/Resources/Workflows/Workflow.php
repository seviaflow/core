<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Workflows;

use InvalidArgumentException;

final class Workflow
{
    private string $stateField = 'status';

    private ?string $initialState = null;

    /**
     * @var array<int, State>
     */
    private array $states = [];

    /**
     * @var array<int, Transition>
     */
    private array $transitions = [];

    public function stateField(string $field): static
    {
        $field = trim($field);

        if ($field === '') {
            throw new InvalidArgumentException('Workflow state field cannot be empty.');
        }

        $this->stateField = $field;

        return $this;
    }

    public function getStateField(): string
    {
        return $this->stateField;
    }

    public function initialState(?string $state): static
    {
        if ($state !== null) {
            $state = trim($state);

            if ($state === '') {
                throw new InvalidArgumentException('Workflow initial state cannot be empty when provided.');
            }
        }

        $this->initialState = $state;

        return $this;
    }

    public function getInitialState(): ?string
    {
        return $this->initialState;
    }

    /**
     * @param  array<int, mixed>  $states
     */
    public function states(array $states): static
    {
        foreach ($states as $index => $state) {
            if (! $state instanceof State) {
                throw new InvalidArgumentException(sprintf(
                    'Workflow state at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    State::class,
                    get_debug_type($state)
                ));
            }
        }

        $this->states = array_values($states);

        return $this;
    }

    /**
     * @return array<int, State>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @param  array<int, mixed>  $transitions
     */
    public function transitions(array $transitions): static
    {
        foreach ($transitions as $index => $transition) {
            if (! $transition instanceof Transition) {
                throw new InvalidArgumentException(sprintf(
                    'Workflow transition at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Transition::class,
                    get_debug_type($transition)
                ));
            }
        }

        $this->transitions = array_values($transitions);

        return $this;
    }

    /**
     * @return array<int, Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getState(string $name): ?State
    {
        foreach ($this->states as $state) {
            if ($state->name() === $name) {
                return $state;
            }
        }

        return null;
    }

    public function getTransition(string $name): ?Transition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->name() === $name) {
                return $transition;
            }
        }

        return null;
    }
}
