<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\ImageField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\PhoneField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\SlugField;
use SeviaFlow\Core\Resources\Fields\TextareaField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\TimeField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Fields\UrlField;

it('creates text field with make and stores name', function (): void {
    $field = TextField::make('name');

    expect($field)->toBeInstanceOf(TextField::class)
        ->and($field->name())->toBe('name');
});

it('creates email field with make and stores name', function (): void {
    $field = EmailField::make('email');

    expect($field)->toBeInstanceOf(EmailField::class)
        ->and($field->name())->toBe('email');
});

it('creates url field with make and stores name', function (): void {
    $field = UrlField::make('website');

    expect($field)->toBeInstanceOf(UrlField::class)
        ->and($field->name())->toBe('website');
});

it('creates phone field with make and stores name', function (): void {
    $field = PhoneField::make('phone');

    expect($field)->toBeInstanceOf(PhoneField::class)
        ->and($field->name())->toBe('phone');
});

it('creates slug field with make and stores name', function (): void {
    $field = SlugField::make('slug');

    expect($field)->toBeInstanceOf(SlugField::class)
        ->and($field->name())->toBe('slug');
});

it('creates multiselect field with make and stores options', function (): void {
    $field = MultiSelectField::make('channels')->options([
        'email' => 'Email',
        'sms' => 'SMS',
    ]);

    expect($field)->toBeInstanceOf(MultiSelectField::class)
        ->and($field->name())->toBe('channels')
        ->and($field->getOptions())->toBe([
            'email' => 'Email',
            'sms' => 'SMS',
        ]);
});

it('creates file field with metadata', function (): void {
    $field = FileField::make('document_path')
        ->disk('public')
        ->directory('documents')
        ->maxSize(2048)
        ->acceptedFileTypes(['application/pdf']);

    expect($field)->toBeInstanceOf(FileField::class)
        ->and($field->name())->toBe('document_path')
        ->and($field->getDisk())->toBe('public')
        ->and($field->getDirectory())->toBe('documents')
        ->and($field->getMaxSize())->toBe(2048)
        ->and($field->getAcceptedFileTypes())->toBe(['application/pdf']);
});

it('creates image field with default accepted types', function (): void {
    $field = ImageField::make('avatar_path');

    expect($field)->toBeInstanceOf(ImageField::class)
        ->and($field->name())->toBe('avatar_path')
        ->and($field->getAcceptedFileTypes())->toContain('image/jpeg', 'image/png');
});

it('stores slug source field metadata', function (): void {
    $field = SlugField::make('slug');

    expect($field->getSourceField())->toBeNull();

    $field->from('name');

    expect($field->getSourceField())->toBe('name');
});

it('creates number field with make and stores name', function (): void {
    $field = NumberField::make('price');

    expect($field)->toBeInstanceOf(NumberField::class)
        ->and($field->name())->toBe('price');
});

it('creates money field with make and stores name', function (): void {
    $field = MoneyField::make('price');

    expect($field)->toBeInstanceOf(MoneyField::class)
        ->and($field->name())->toBe('price');
});

it('stores money field currency and default currency', function (): void {
    config()->set('seviaflow.forms.default_currency', 'eur');

    $field = MoneyField::make('price');

    expect($field->getCurrency())->toBe('EUR');

    $field->currency('pln');

    expect($field->getCurrency())->toBe('PLN');
});

it('supports min and max on money field', function (): void {
    $field = MoneyField::make('price')->min(1)->max(999.99);

    expect($field->getMin())->toBe(1)
        ->and($field->getMax())->toBe(999.99);
});

it('creates textarea field with make and stores name', function (): void {
    $field = TextareaField::make('description');

    expect($field)->toBeInstanceOf(TextareaField::class)
        ->and($field->name())->toBe('description');
});

it('creates toggle field with make and stores name', function (): void {
    $field = ToggleField::make('is_active');

    expect($field)->toBeInstanceOf(ToggleField::class)
        ->and($field->name())->toBe('is_active');
});

it('creates select field with make and stores name', function (): void {
    $field = SelectField::make('status');

    expect($field)->toBeInstanceOf(SelectField::class)
        ->and($field->name())->toBe('status');
});

it('creates date field with make and stores name', function (): void {
    $field = DateField::make('published_at');

    expect($field)->toBeInstanceOf(DateField::class)
        ->and($field->name())->toBe('published_at');
});

it('creates datetime field with make and stores name', function (): void {
    $field = DateTimeField::make('published_at');

    expect($field)->toBeInstanceOf(DateTimeField::class)
        ->and($field->name())->toBe('published_at');
});

it('creates time field with make and stores name', function (): void {
    $field = TimeField::make('opening_time');

    expect($field)->toBeInstanceOf(TimeField::class)
        ->and($field->name())->toBe('opening_time');
});

it('creates password field with make and stores name', function (): void {
    $field = PasswordField::make('password');

    expect($field)->toBeInstanceOf(PasswordField::class)
        ->and($field->name())->toBe('password');
});

it('stores password dehydrate only when filled flag', function (): void {
    $field = PasswordField::make('password');

    expect($field->shouldDehydrateOnlyWhenFilled())->toBeTrue();

    $field->dehydrateOnlyWhenFilled(false);

    expect($field->shouldDehydrateOnlyWhenFilled())->toBeFalse();
});

it('supports inherited label required help and placeholder behavior', function (): void {
    $field = TextField::make('first_name');

    expect($field->getLabel())->toBe('First name')
        ->and($field->isRequired())->toBeFalse()
        ->and($field->getHelp())->toBeNull()
        ->and($field->getPlaceholder())->toBeNull();

    $field
        ->label('Given name')
        ->required()
        ->help('Visible to operators')
        ->placeholder('Enter name');

    expect($field->getLabel())->toBe('Given name')
        ->and($field->isRequired())->toBeTrue()
        ->and($field->getHelp())->toBe('Visible to operators')
        ->and($field->getPlaceholder())->toBe('Enter name');
});

it('stores number field min and max', function (): void {
    $field = NumberField::make('price');

    expect($field->getMin())->toBeNull()
        ->and($field->getMax())->toBeNull();

    $field->min(1)->max(99.5);

    expect($field->getMin())->toBe(1)
        ->and($field->getMax())->toBe(99.5);
});

it('stores textarea rows', function (): void {
    $field = TextareaField::make('description');

    expect($field->getRows())->toBe(3);

    $field->rows(6);

    expect($field->getRows())->toBe(6);
});

it('stores select options', function (): void {
    $options = [
        'draft' => 'Draft',
        'published' => 'Published',
    ];

    $field = SelectField::make('status')->options($options);

    expect($field->getOptions())->toBe($options);
});

it('allows using toggle field as base field type', function (): void {
    $field = ToggleField::make('is_active');

    expect($field)->toBeInstanceOf(Field::class);
});

it('allows using date field as base field type', function (): void {
    $field = DateField::make('published_at');

    expect($field)->toBeInstanceOf(Field::class);
});
