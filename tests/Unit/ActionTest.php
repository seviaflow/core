<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Action;

it('can create action with name', function (): void {
    $action = Action::make('deactivate');

    expect($action->name())->toBe('deactivate');
});

it('uses human-readable label by default', function (): void {
    expect(Action::make('deactivate_account')->getLabel())->toBe('Deactivate account')
        ->and(Action::make('mark-as-paid')->getLabel())->toBe('Mark as paid');
});

it('stores label and description', function (): void {
    $action = Action::make('verify')
        ->label('Verify customer')
        ->description('The customer will be marked as verified.');

    expect($action->getLabel())->toBe('Verify customer')
        ->and($action->getDescription())->toBe('The customer will be marked as verified.');
});

it('supports danger flag with safe confirmation default', function (): void {
    $action = Action::make('deactivate')->danger();

    expect($action->isDanger())->toBeTrue()
        ->and($action->shouldRequireConfirmation())->toBeTrue()
        ->and($action->isPrimary())->toBeFalse()
        ->and($action->isSecondary())->toBeFalse();
});

it('supports confirmation metadata', function (): void {
    $action = Action::make('cancel')
        ->requiresConfirmation()
        ->confirmButton('Cancel order')
        ->cancelButton('Keep order');

    expect($action->shouldRequireConfirmation())->toBeTrue()
        ->and($action->getConfirmButton())->toBe('Cancel order')
        ->and($action->getCancelButton())->toBe('Keep order');
});

it('stores success and error messages', function (): void {
    $action = Action::make('send_invitation')
        ->successMessage('Invitation sent.')
        ->errorMessage('Invitation could not be sent.');

    expect($action->getSuccessMessage())->toBe('Invitation sent.')
        ->and($action->getErrorMessage())->toBe('Invitation could not be sent.');
});

it('executes handler with record', function (): void {
    $record = new class
    {
        public bool $processed = false;
    };

    $result = Action::make('process')
        ->handle(function (object $row): bool {
            $row->processed = true;

            return true;
        })
        ->execute($record);

    expect($result)->toBeTrue()
        ->and($record->processed)->toBeTrue();
});

it('throws clear exception when executing action without handler', function (): void {
    expect(fn () => Action::make('no_handler')->execute(new stdClass))
        ->toThrow(LogicException::class, 'no handler was configured');
});
