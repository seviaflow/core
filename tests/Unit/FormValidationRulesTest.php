<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\ImageField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\PhoneField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\SlugField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\TimeField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Fields\UrlField;
use SeviaFlow\Core\Resources\ResourceContext;

it('builds rules for visible editable fields in create context', function (): void {
    $fields = [
        TextField::make('name')->label('Name')->required(),
        EmailField::make('email')->label('Email address'),
        UrlField::make('website')->label('Website'),
        PhoneField::make('phone')->label('Phone'),
        SlugField::make('slug')->label('Slug'),
        MultiSelectField::make('channels')->label('Channels')->options([
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push',
        ])->minSelected(1)->maxSelected(3),
        FileField::make('document_path')->label('Document')->maxSize(2048)->acceptedFileTypes(['application/pdf']),
        ImageField::make('avatar_path')->label('Avatar')->maxSize(1024),
        NumberField::make('age')->label('Age')->min(1)->max(100),
        MoneyField::make('price')->label('Price')->currency('USD')->min(0)->max(9999.99),
        SelectField::make('status')->label('Status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ]),
        DateField::make('published_at')->label('Published at'),
        DateTimeField::make('starts_at')->label('Starts at'),
        TimeField::make('opening_time')->label('Opening time'),
        ToggleField::make('is_active')->label('Active'),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.name'])->toContain('required')
        ->and($rules['fieldValues.email'])->toContain('nullable', 'email')
        ->and($rules['fieldValues.website'])->toContain('nullable', 'url')
        ->and($rules['fieldValues.phone'])->toContain('nullable', 'string', 'regex:/^\+?[0-9\s().-]+$/')
        ->and($rules['fieldValues.slug'])->toContain('nullable', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
        ->and($rules['fieldValues.channels'])->toContain('nullable', 'array', 'min:1', 'max:3')
        ->and($rules['fieldValues.document_path'])->toContain('nullable', 'file', 'max:2048', 'mimetypes:application/pdf')
        ->and($rules['fieldValues.avatar_path'])->toContain('nullable', 'file', 'image', 'max:1024')
        ->and($rules['fieldValues.age'])->toContain('nullable', 'numeric', 'min:1', 'max:100')
        ->and($rules['fieldValues.price'])->toContain('nullable', 'numeric', 'min:0', 'max:9999.99')
        ->and($rules['fieldValues.published_at'])->toContain('nullable', 'date')
        ->and($rules['fieldValues.starts_at'])->toContain('nullable', 'date')
        ->and($rules['fieldValues.opening_time'])->toContain('nullable', 'date_format:H:i')
        ->and($rules['fieldValues.is_active'])->toContain('nullable', 'boolean')
        ->and(collect($rules['fieldValues.channels.*'])->contains(
            static fn (mixed $rule): bool => $rule instanceof In
        ))->toBeTrue()
        ->and(collect($rules['fieldValues.status'])->contains(
            static fn (mixed $rule): bool => $rule instanceof In
        ))->toBeTrue();
});

it('excludes hidden readonly and disabled fields from rules and attributes', function (): void {
    $fields = [
        TextField::make('name')->label('Name')->required(),
        TextField::make('hidden_notes')->label('Hidden notes')->hiddenOnEdit(),
        TextField::make('slug')->label('Slug')->readonlyOnEdit(),
        SelectField::make('status')->label('Status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])->disabledOnEdit(),
    ];

    $rulesBuilder = new FormValidationRules;

    $rules = $rulesBuilder->build($fields, ResourceContext::Edit);
    $attributes = $rulesBuilder->attributes($fields, ResourceContext::Edit);

    expect(array_keys($rules))->toBe(['fieldValues.name'])
        ->and(array_keys($attributes))->toBe(['fieldValues.name'])
        ->and($attributes['fieldValues.name'])->toBe('name');
});

it('merges field rules into laravel validation rules', function (): void {
    $fields = [
        TextField::make('name')->rules('string|max:255'),
        TextField::make('bio')->required()->rules(['string', 'required']),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.name'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['fieldValues.bio'])->toContain('required', 'string');

    $requiredCount = count(array_filter(
        $rules['fieldValues.bio'],
        static fn (mixed $rule): bool => is_string($rule) && strtolower($rule) === 'required'
    ));

    expect($requiredCount)->toBe(1);
});

it('field defaults merge with custom rules without duplication', function (): void {
    $fields = [
        EmailField::make('email')->required()->rules(['email', 'max:255']),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.email'])->toContain('required', 'email', 'max:255');

    $emailCount = count(array_filter(
        $rules['fieldValues.email'],
        static fn (mixed $rule): bool => is_string($rule) && strtolower($rule) === 'email'
    ));

    expect($emailCount)->toBe(1);
});

it('can disable default field type validation rules when needed', function (): void {
    $fields = [
        EmailField::make('email')->withoutDefaultValidation()->rules('string|max:255'),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.email'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['fieldValues.email'])->not->toContain('email');
});

it('applies rulesOnCreate only for create context', function (): void {
    $fields = [
        TextField::make('name')->rules('string')->rulesOnCreate('max:255'),
    ];

    $builder = new FormValidationRules;

    $createRules = $builder->build($fields, ResourceContext::Create);
    $editRules = $builder->build($fields, ResourceContext::Edit);

    expect($createRules['fieldValues.name'])->toContain('max:255')
        ->and($editRules['fieldValues.name'])->not->toContain('max:255');
});

it('applies rulesOnEdit only for edit context', function (): void {
    $fields = [
        TextField::make('name')->rules('string')->rulesOnEdit('min:3'),
    ];

    $builder = new FormValidationRules;

    $createRules = $builder->build($fields, ResourceContext::Create);
    $editRules = $builder->build($fields, ResourceContext::Edit);

    expect($createRules['fieldValues.name'])->not->toContain('min:3')
        ->and($editRules['fieldValues.name'])->toContain('min:3');
});

it('requiredOnCreate does not validate on edit', function (): void {
    $fields = [
        TextField::make('password')->requiredOnCreate(),
    ];

    $builder = new FormValidationRules;
    $createRules = $builder->build($fields, ResourceContext::Create);
    $editRules = $builder->build($fields, ResourceContext::Edit);

    expect($createRules['fieldValues.password'])->toContain('required')
        ->and($editRules['fieldValues.password'])->toContain('nullable')
        ->and($editRules['fieldValues.password'])->not->toContain('required');
});

it('show context does not build writable validation rules', function (): void {
    $fields = [
        TextField::make('name')->required()->rules('string|max:255'),
    ];

    $builder = new FormValidationRules;

    expect($builder->build($fields, ResourceContext::Show))->toBe([])
        ->and($builder->attributes($fields, ResourceContext::Show))->toBe([]);
});

it('hiddenOnEdit field is not validated on edit', function (): void {
    $fields = [
        TextField::make('name')->rules('string')->hiddenOnEdit(),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Edit);

    expect($rules)->toBe([]);
});

it('readonlyOnEdit field is not validated on edit', function (): void {
    $fields = [
        TextField::make('name')->rules('string')->readonlyOnEdit(),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Edit);

    expect($rules)->toBe([]);
});

it('disabledOnEdit field is not validated on edit', function (): void {
    $fields = [
        TextField::make('name')->rules('string')->disabledOnEdit(),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Edit);

    expect($rules)->toBe([]);
});

it('evaluates closure rules with record and context', function (): void {
    $record = new ValidationRecord;
    $record->id = 15;

    $fields = [
        EmailField::make('email')->rules(function (?ValidationRecord $passedRecord, ResourceContext $context): array {
            return [
                $passedRecord?->id === 15 ? 'max:255' : 'max:120',
                $context === ResourceContext::Edit ? 'ends_with:@example.com' : 'email',
            ];
        }),
    ];

    $builder = new FormValidationRules;
    $rules = $builder->build($fields, ResourceContext::Edit, null, 'fieldValues', [], $record);

    expect($rules['fieldValues.email'])->toContain('nullable', 'email', 'max:255', 'ends_with:@example.com');
});

it('supports laravel Rule objects returned from closure rules', function (): void {
    $record = new ValidationRecord;
    $record->id = 42;

    $fields = [
        EmailField::make('email')->rules(function (?ValidationRecord $passedRecord, ResourceContext $context): array {
            return [
                Rule::unique('users', 'email')->ignore($passedRecord?->id),
                $context === ResourceContext::Edit ? 'max:255' : 'max:120',
            ];
        }),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Edit, null, 'fieldValues', [], $record);

    expect(collect($rules['fieldValues.email'])->contains(
        static fn (mixed $rule): bool => $rule instanceof Unique
    ))->toBeTrue()
        ->and($rules['fieldValues.email'])->toContain('nullable', 'email', 'max:255');
});

it('fails clearly when closure rule returns invalid type', function (): void {
    $fields = [
        EmailField::make('email')->rules(
            fn (): int => 123
        ),
    ];

    expect(fn () => (new FormValidationRules)->build($fields, ResourceContext::Create))
        ->toThrow(InvalidArgumentException::class, 'rules must be a string, array, closure, or rule object');
});

it('merges closure rules with required rules', function (): void {
    $fields = [
        EmailField::make('email')
            ->required()
            ->rules(fn (): array => ['max:255']),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.email'])->toContain('required', 'email', 'max:255')
        ->and($rules['fieldValues.email'])->not->toContain('nullable');
});

it('supports fluent text validation helpers', function (): void {
    $fields = [
        TextField::make('name')
            ->required()
            ->minLength(3)
            ->maxLength(50)
            ->length(10)
            ->regex('/^[A-Za-z\\s]+$/'),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    expect($rules['fieldValues.name'])->toContain(
        'required',
        'string',
        'min:3',
        'max:50',
        'size:10',
        'regex:/^[A-Za-z\\s]+$/'
    )->and($rules['fieldValues.name'])->not->toContain('nullable');
});

it('deduplicates helper rules with explicit rules input', function (): void {
    $fields = [
        TextField::make('name')
            ->maxLength(255)
            ->rules('max:255|string'),
    ];

    $rules = (new FormValidationRules)->build($fields, ResourceContext::Create);

    $maxCount = count(array_filter(
        $rules['fieldValues.name'],
        static fn (mixed $rule): bool => is_string($rule) && strtolower($rule) === 'max:255'
    ));

    expect($maxCount)->toBe(1)
        ->and($rules['fieldValues.name'])->toContain('nullable', 'string', 'max:255');
});

/**
 * @property int $id
 */
final class ValidationRecord extends Model
{
    protected $table = 'validation_records';

    protected $guarded = [];
}
