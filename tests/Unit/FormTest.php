<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Section;
use Tests\Fixtures\Fields\DummyField;

it('registers fields', function (): void {
    $nameField = DummyField::make('name');
    $emailField = DummyField::make('email');

    $form = (new Form)->fields([$nameField, $emailField]);

    expect($form->getFields())
        ->toHaveCount(2)
        ->and($form->getFields()[0])->toBe($nameField)
        ->and($form->getFields()[1])->toBe($emailField);
});

it('rejects invalid field definitions', function (): void {
    expect(fn () => (new Form)->fields([DummyField::make('name'), 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});

it('stores sections', function (): void {
    $first = Section::make('Basic')->fields([DummyField::make('name')]);
    $second = Section::make('Status')->fields([DummyField::make('is_active')]);

    $form = (new Form)->sections([$first, $second]);

    expect($form->getSections())
        ->toHaveCount(2)
        ->and($form->getSections()[0])->toBe($first)
        ->and($form->getSections()[1])->toBe($second);
});

it('rejects invalid section definitions', function (): void {
    expect(fn () => (new Form)->sections([Section::make('Basic'), 'invalid']))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});

it('returns all fields from sections', function (): void {
    $nameField = DummyField::make('name');
    $emailField = DummyField::make('email');

    $form = (new Form)->sections([
        Section::make('Basic')->fields([$nameField]),
        Section::make('Contact')->fields([$emailField]),
    ]);

    expect($form->getAllFields())
        ->toHaveCount(2)
        ->and($form->getAllFields()[0])->toBe($nameField)
        ->and($form->getAllFields()[1])->toBe($emailField);
});
