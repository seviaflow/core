<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ImportResource;

it('is disabled by default with empty allowed and required fields', function (): void {
    $import = new ImportResource;

    expect($import->isEnabled())->toBeFalse()
        ->and($import->getAllowedFields())->toBe([])
        ->and($import->getRequiredFields())->toBe([])
        ->and($import->usesFormFieldRules())->toBeFalse();
});

it('can enable and disable conditionally', function (): void {
    $import = new ImportResource;

    $import->enabled(false);
    expect($import->isEnabled())->toBeFalse();

    $import->enabled();
    expect($import->isEnabled())->toBeTrue();

    $import->disabled(false);
    expect($import->isEnabled())->toBeTrue();

    $import->disabled();
    expect($import->isEnabled())->toBeFalse();
});

it('can set allowed fields with normalization', function (): void {
    $import = (new ImportResource)->allowedFields([' name ', 'email', 'email', 'profile.status']);

    expect($import->getAllowedFields())->toBe(['name', 'email', 'profile.status']);
});

it('can set required fields with normalization', function (): void {
    $import = (new ImportResource)->requiredFields([' name ', 'email', 'email']);

    expect($import->getRequiredFields())->toBe(['name', 'email']);
});

it('fails clearly when field is not a string', function (): void {
    expect(fn () => (new ImportResource)->allowedFields(['name', 123]))
        ->toThrow(InvalidArgumentException::class, 'ImportResource::allowedFields field at index 1 must be a non-empty string');
});

it('fails clearly when field is empty', function (): void {
    expect(fn () => (new ImportResource)->requiredFields(['name', '  ']))
        ->toThrow(InvalidArgumentException::class, 'ImportResource::requiredFields field at index 1 must be a non-empty string.');
});

it('fails clearly when field name is invalid', function (): void {
    expect(fn () => (new ImportResource)->allowedFields(['invalid-field']))
        ->toThrow(InvalidArgumentException::class, 'ImportResource::allowedFields field [invalid-field] is invalid');
});

it('can enable using form field rules conditionally', function (): void {
    $import = new ImportResource;

    $import->useFormFieldRules(false);
    expect($import->usesFormFieldRules())->toBeFalse();

    $import->useFormFieldRules();
    expect($import->usesFormFieldRules())->toBeTrue();
});
