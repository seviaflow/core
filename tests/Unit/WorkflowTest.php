<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;

it('stores workflow state field', function (): void {
    $workflow = (new Workflow)->stateField('status');

    expect($workflow->getStateField())->toBe('status');
});

it('stores workflow initial state', function (): void {
    $workflow = (new Workflow)->initialState('draft');

    expect($workflow->getInitialState())->toBe('draft');
});

it('stores workflow states', function (): void {
    $states = [
        State::make('draft'),
        State::make('paid'),
    ];

    $workflow = (new Workflow)->states($states);

    expect($workflow->getStates())->toHaveCount(2)
        ->and($workflow->getState('draft'))->toBeInstanceOf(State::class)
        ->and($workflow->getState('missing'))->toBeNull();
});

it('rejects invalid workflow states', function (): void {
    /** @var array<int, State> $states */
    $states = [new stdClass];

    expect(fn () => (new Workflow)->states($states))
        ->toThrow(InvalidArgumentException::class, 'Workflow state at index');
});

it('stores workflow transitions', function (): void {
    $transitions = [
        Transition::make('submit')->from('draft')->to('pending'),
        Transition::make('pay')->from('pending')->to('paid'),
    ];

    $workflow = (new Workflow)->transitions($transitions);

    expect($workflow->getTransitions())->toHaveCount(2)
        ->and($workflow->getTransition('submit'))->toBeInstanceOf(Transition::class)
        ->and($workflow->getTransition('missing'))->toBeNull();
});

it('rejects invalid workflow transitions', function (): void {
    /** @var array<int, Transition> $transitions */
    $transitions = [new stdClass];

    expect(fn () => (new Workflow)->transitions($transitions))
        ->toThrow(InvalidArgumentException::class, 'Workflow transition at index');
});

it('stores state metadata', function (): void {
    $state = State::make('pending_payment')
        ->label('Pending payment')
        ->color('yellow')
        ->final();

    expect($state->name())->toBe('pending_payment')
        ->and($state->getLabel())->toBe('Pending payment')
        ->and($state->getColor())->toBe('yellow')
        ->and($state->isFinal())->toBeTrue();
});

it('uses humanized state label by default', function (): void {
    expect(State::make('pending_payment')->getLabel())->toBe('Pending payment');
});

it('rejects unsafe state color', function (): void {
    expect(fn () => State::make('draft')->color('bg-red-500'))
        ->toThrow(InvalidArgumentException::class, 'is not allowed');
});

it('stores transition from and to states', function (): void {
    $transition = Transition::make('submit')
        ->from('draft')
        ->to('pending');

    expect($transition->getFromStates())->toBe(['draft'])
        ->and($transition->getToState())->toBe('pending');
});

it('supports multiple transition from states', function (): void {
    $transition = Transition::make('cancel')
        ->from(['draft', 'pending', 'draft'])
        ->to('cancelled');

    expect($transition->getFromStates())->toBe(['draft', 'pending']);
});

it('stores transition flags and messages', function (): void {
    $transition = Transition::make('cancel')
        ->danger()
        ->requiresComment()
        ->successMessage('Order cancelled.')
        ->errorMessage('Order could not be cancelled.');

    expect($transition->isDanger())->toBeTrue()
        ->and($transition->shouldRequireConfirmation())->toBeTrue()
        ->and($transition->shouldRequireComment())->toBeTrue()
        ->and($transition->getSuccessMessage())->toBe('Order cancelled.')
        ->and($transition->getErrorMessage())->toBe('Order could not be cancelled.');
});

it('uses humanized transition label by default', function (): void {
    expect(Transition::make('pending_payment')->getLabel())->toBe('Pending payment');
});
