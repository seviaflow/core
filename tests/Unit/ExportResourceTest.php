<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;

it('is disabled by default with empty fields and csv format', function (): void {
    $export = new ExportResource;

    expect($export->isEnabled())->toBeFalse()
        ->and($export->getAllowedFields())->toBe([])
        ->and($export->getFormats())->toBe(['csv']);
});

it('can enable and disable conditionally', function (): void {
    $export = new ExportResource;

    $export->enabled(false);
    expect($export->isEnabled())->toBeFalse();

    $export->enabled();
    expect($export->isEnabled())->toBeTrue();

    $export->disabled(false);
    expect($export->isEnabled())->toBeTrue();

    $export->disabled();
    expect($export->isEnabled())->toBeFalse();
});

it('can set allowed fields and preserve labels', function (): void {
    $export = (new ExportResource)->allowedFields([
        ExportField::make('id')->label('ID'),
        ExportField::make('name')->label('Name'),
        ExportField::make('name')->label('Duplicate should be ignored'),
    ]);

    $fields = $export->getAllowedFields();

    expect($fields)->toHaveCount(2)
        ->and($fields[0]->name())->toBe('id')
        ->and($fields[0]->getLabel())->toBe('ID')
        ->and($fields[1]->name())->toBe('name')
        ->and($fields[1]->getLabel())->toBe('Name');
});

it('can set allowed formats with normalization', function (): void {
    $export = (new ExportResource)->formats([' CSV ', 'csv']);

    expect($export->getFormats())->toBe(['csv']);
});

it('fails clearly when allowed field item is invalid', function (): void {
    expect(fn () => (new ExportResource)->allowedFields([ExportField::make('id'), 'name']))
        ->toThrow(InvalidArgumentException::class, 'ExportResource::allowedFields value at index 1 must be an instance');
});

it('fails clearly when format is invalid', function (): void {
    expect(fn () => (new ExportResource)->formats(['xlsx']))
        ->toThrow(InvalidArgumentException::class, 'ExportResource::formats value [xlsx] is not supported');
});

it('fails clearly when format is empty', function (): void {
    expect(fn () => (new ExportResource)->formats(['  ']))
        ->toThrow(InvalidArgumentException::class, 'ExportResource::formats value at index 0 must be a non-empty string.');
});

it('builds export field labels with explicit label and fallback', function (): void {
    $explicit = ExportField::make('email')->label('Email address');
    $fallback = ExportField::make('created_at');

    expect($explicit->name())->toBe('email')
        ->and($explicit->getLabel())->toBe('Email address')
        ->and($fallback->name())->toBe('created_at')
        ->and($fallback->getLabel())->toBe('Created At');
});

it('fails clearly when export field name or label is invalid', function (): void {
    expect(fn () => ExportField::make(''))
        ->toThrow(InvalidArgumentException::class, 'ExportField name must be a non-empty string.');

    expect(fn () => ExportField::make('invalid-field'))
        ->toThrow(InvalidArgumentException::class, 'ExportField name [invalid-field] is invalid.');

    expect(fn () => ExportField::make('name')->label('  '))
        ->toThrow(InvalidArgumentException::class, 'ExportField label must be a non-empty string.');
});
