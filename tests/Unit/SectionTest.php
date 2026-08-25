<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Section;
use Tests\Fixtures\Fields\DummyField;

it('stores label', function (): void {
    $section = Section::make('Basic information');

    expect($section->label())->toBe('Basic information');
});

it('stores description', function (): void {
    $section = Section::make('Status')
        ->description('Controls account visibility.');

    expect($section->getDescription())->toBe('Controls account visibility.');
});

it('stores fields', function (): void {
    $nameField = DummyField::make('name');
    $emailField = DummyField::make('email');

    $section = Section::make('Basic information')
        ->fields([$nameField, $emailField]);

    expect($section->getFields())
        ->toHaveCount(2)
        ->and($section->getFields()[0])->toBe($nameField)
        ->and($section->getFields()[1])->toBe($emailField);
});

it('rejects invalid fields', function (): void {
    expect(fn () => Section::make('Invalid')->fields([DummyField::make('name'), 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});
