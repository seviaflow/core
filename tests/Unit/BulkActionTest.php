<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use SeviaFlow\Core\Resources\BulkAction;

it('can create bulk action with name', function (): void {
    $action = BulkAction::make('deactivate');

    expect($action->name())->toBe('deactivate');
});

it('uses human-readable label by default', function (): void {
    expect(BulkAction::make('deactivate_account')->getLabel())->toBe('Deactivate account')
        ->and(BulkAction::make('mark-as-paid')->getLabel())->toBe('Mark as paid');
});

it('stores label and description', function (): void {
    $action = BulkAction::make('verify_many')
        ->label('Verify selected customers')
        ->description('Selected customers will be marked as verified.');

    expect($action->getLabel())->toBe('Verify selected customers')
        ->and($action->getDescription())->toBe('Selected customers will be marked as verified.');
});

it('supports danger flag with safe confirmation default', function (): void {
    $action = BulkAction::make('deactivate')->danger();

    expect($action->isDanger())->toBeTrue()
        ->and($action->shouldRequireConfirmation())->toBeTrue();
});

it('supports confirmation metadata', function (): void {
    $action = BulkAction::make('cancel_many')
        ->requiresConfirmation()
        ->confirmButton('Cancel selected orders')
        ->cancelButton('Keep selected orders');

    expect($action->shouldRequireConfirmation())->toBeTrue()
        ->and($action->getConfirmButton())->toBe('Cancel selected orders')
        ->and($action->getCancelButton())->toBe('Keep selected orders');
});

it('stores max records metadata', function (): void {
    $action = BulkAction::make('deactivate_many')
        ->maxRecords(50);

    expect($action->getMaxRecords())->toBe(50);
});

it('stores success and error messages', function (): void {
    $action = BulkAction::make('send_invitation_many')
        ->successMessage('Invitations sent.')
        ->errorMessage('Invitations could not be sent.');

    expect($action->getSuccessMessage())->toBe('Invitations sent.')
        ->and($action->getErrorMessage())->toBe('Invitations could not be sent.');
});

it('executes handler with selected records collection', function (): void {
    $records = collect([
        (object) ['processed' => false],
        (object) ['processed' => false],
    ]);

    $result = BulkAction::make('process_many')
        ->handle(function (Collection $rows): int {
            $rows->each(function (object $row): void {
                $row->processed = true;
            });

            return $rows->count();
        })
        ->execute($records);

    expect($result)->toBe(2)
        ->and($records->every(fn (object $row): bool => $row->processed))->toBeTrue();
});

it('throws clear exception when executing bulk action without handler', function (): void {
    expect(fn () => BulkAction::make('no_handler')->execute(collect()))
        ->toThrow(LogicException::class, 'no handler was configured');
});

it('supports explicit authorization callback', function (): void {
    $allowed = BulkAction::make('authorize-many')
        ->authorize(fn (?object $user, Collection $records): bool => $user !== null && $records->count() === 2)
        ->canExecute((object) ['id' => 1], collect([1, 2]));

    $denied = BulkAction::make('authorize-many')
        ->authorize(fn (?object $user, Collection $records): bool => $user !== null && $records->count() === 2)
        ->canExecute(null, collect([1, 2]));

    expect($allowed)->toBeTrue()
        ->and($denied)->toBeFalse();
});

it('declares read-only and mutating operation modes', function (): void {
    $readOnly = BulkAction::make('preview')->readOnly();
    $mutating = BulkAction::make('archive')->mutating();

    expect($readOnly->isReadOnly())->toBeTrue()
        ->and($readOnly->isMutating())->toBeFalse()
        ->and($mutating->isReadOnly())->toBeFalse()
        ->and($mutating->isMutating())->toBeTrue();
});
