<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ApiResource;

it('is disabled by default with empty field lists and non deletable state', function (): void {
    $api = new ApiResource;

    expect($api->isEnabled())->toBeFalse()
        ->and($api->isDeletable())->toBeFalse()
        ->and($api->isFlowEnabled())->toBeFalse()
        ->and($api->getReadableFields())->toBe([])
        ->and($api->getCreatableFields())->toBe([])
        ->and($api->getUpdatableFields())->toBe([])
        ->and($api->getRequiredOnCreateFields())->toBe([])
        ->and($api->getRequiredOnUpdateFields())->toBe([])
        ->and($api->usesFormFieldRules())->toBeFalse();
});

it('can enable and disable conditionally', function (): void {
    $api = new ApiResource;

    $api->enabled(false);
    expect($api->isEnabled())->toBeFalse();

    $api->enabled();
    expect($api->isEnabled())->toBeTrue();

    $api->disabled(false);
    expect($api->isEnabled())->toBeTrue();

    $api->disabled();
    expect($api->isEnabled())->toBeFalse();
});

it('can set readable fields with normalization', function (): void {
    $api = (new ApiResource)->readable([' id ', 'name', 'name', 'profile.email']);

    expect($api->getReadableFields())->toBe(['id', 'name', 'profile.email']);
});

it('can set creatable fields with normalization', function (): void {
    $api = (new ApiResource)->creatable([' name ', 'email', 'email']);

    expect($api->getCreatableFields())->toBe(['name', 'email']);
});

it('can set updatable fields with normalization', function (): void {
    $api = (new ApiResource)->updatable([' name ', 'status', 'status']);

    expect($api->getUpdatableFields())->toBe(['name', 'status']);
});

it('can set required on create fields with normalization', function (): void {
    $api = (new ApiResource)->requiredOnCreate([' name ', 'email', 'email']);

    expect($api->getRequiredOnCreateFields())->toBe(['name', 'email']);
});

it('can set required on update fields with normalization', function (): void {
    $api = (new ApiResource)->requiredOnUpdate([' status ', 'status', 'profile.name']);

    expect($api->getRequiredOnUpdateFields())->toBe(['status', 'profile.name']);
});

it('stores explicit field schemas for headless resources', function (): void {
    $api = (new ApiResource)->schema([
        'uuid' => ['type' => 'string', 'format' => 'uuid'],
        'invoice_number' => ['type' => 'string'],
    ]);

    expect($api->schemaFor('uuid'))->toBe(['type' => 'string', 'format' => 'uuid'])
        ->and($api->getSchema())->toHaveKeys(['uuid', 'invoice_number']);
});

it('can enable deletable conditionally', function (): void {
    $api = new ApiResource;

    $api->deletable(false);
    expect($api->isDeletable())->toBeFalse();

    $api->deletable();
    expect($api->isDeletable())->toBeTrue();
});

it('uses enabled state and field capabilities for api operations', function (): void {
    $api = (new ApiResource)
        ->enabled()
        ->readable(['name'])
        ->creatable(['name'])
        ->updatable(['name'])
        ->deletable()
        ->flow();

    expect($api->isReadable())->toBeTrue()
        ->and($api->isCreatable())->toBeTrue()
        ->and($api->isUpdatable())->toBeTrue()
        ->and($api->allowsDelete())->toBeTrue()
        ->and($api->allowsFlow())->toBeTrue();

    $api->disabled();

    expect($api->isReadable())->toBeFalse()
        ->and($api->isCreatable())->toBeFalse()
        ->and($api->isUpdatable())->toBeFalse()
        ->and($api->allowsDelete())->toBeFalse()
        ->and($api->allowsFlow())->toBeFalse();
});

it('can enable flow conditionally', function (): void {
    $api = new ApiResource;

    $api->flow(false);
    expect($api->isFlowEnabled())->toBeFalse();

    $api->flow();
    expect($api->isFlowEnabled())->toBeTrue();
});

it('can enable using form field rules conditionally', function (): void {
    $api = new ApiResource;

    $api->useFormFieldRules(false);
    expect($api->usesFormFieldRules())->toBeFalse();

    $api->useFormFieldRules();
    expect($api->usesFormFieldRules())->toBeTrue();
});

it('fails clearly when field is not string', function (): void {
    expect(fn () => (new ApiResource)->readable(['name', 123]))
        ->toThrow(InvalidArgumentException::class, 'ApiResource::readable field at index 1 must be a non-empty string');
});

it('fails clearly when field is empty', function (): void {
    expect(fn () => (new ApiResource)->creatable(['name', '  ']))
        ->toThrow(InvalidArgumentException::class, 'ApiResource::creatable field at index 1 must be a non-empty string.');
});

it('fails clearly when field contains invalid characters', function (): void {
    expect(fn () => (new ApiResource)->updatable(['name', 'invalid-field']))
        ->toThrow(InvalidArgumentException::class, 'ApiResource::updatable field [invalid-field] is invalid');
});
