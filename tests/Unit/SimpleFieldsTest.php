<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Resources\Fields\CheckboxGroupField;
use SeviaFlow\Core\Resources\Fields\ColorField;
use SeviaFlow\Core\Resources\Fields\JsonField;
use SeviaFlow\Core\Resources\Fields\KeyValueField;
use SeviaFlow\Core\Resources\Fields\RadioGroupField;
use SeviaFlow\Core\Resources\Fields\TagsField;

it('normalizes radio and checkbox values against explicit option contracts', function (): void {
    $radio = RadioGroupField::make('kind')->options(['person' => 'Person', 'company' => 'Company']);
    $checkbox = CheckboxGroupField::make('channels')
        ->options(['email' => 'Email', 'sms' => 'SMS'])
        ->maxSelected(2);

    expect($radio->normalizeValue(' company '))->toBe('company')
        ->and($radio->getSchema()['enum'])->toBe(['person', 'company'])
        ->and($checkbox->normalizeValue(['email', 'email', ' sms ']))->toBe(['email', 'sms'])
        ->and($checkbox->getSchema()['uniqueItems'])->toBeTrue();

    $validator = Validator::make(
        ['channels' => ['fax']],
        [
            'channels' => array_merge(['array'], $checkbox->defaultValidationRules()),
            'channels.*' => $checkbox->defaultNestedValidationRules()['*'],
        ]
    );

    expect($validator->fails())->toBeTrue();
});

it('normalizes tags and enforces collection limits', function (): void {
    $field = TagsField::make('tags')->maxTags(2)->tagLength(8);

    expect($field->prepareForValidation(' alpha, beta, alpha '))->toBe(['alpha', 'beta'])
        ->and($field->formatForInput(['alpha', 'beta']))->toBe('alpha, beta')
        ->and($field->getSchema()['items'])->toBe(['type' => 'string', 'maxLength' => 8]);

    $validator = Validator::make(
        ['tags' => ['one', 'two', 'three']],
        ['tags' => $field->defaultValidationRules(), 'tags.*' => $field->defaultNestedValidationRules()['*']]
    );

    expect($validator->fails())->toBeTrue();
});

it('keeps key value data as a flat object and rejects lists', function (): void {
    $field = KeyValueField::make('metadata')->maxEntries(2);

    expect($field->prepareForValidation('{"region":"EU"}'))->toBe(['region' => 'EU'])
        ->and($field->normalizeValue('{}'))->toBeInstanceOf(stdClass::class)
        ->and($field->formatForInput([]))->toBe('{}')
        ->and(fn () => $field->normalizeValue('[]'))->toThrow(InvalidArgumentException::class);

    $validator = Validator::make(
        ['metadata' => ['first', 'second']],
        ['metadata' => $field->defaultValidationRules()]
    );

    expect($validator->fails())->toBeTrue();
});

it('parses JSON with an explicit root and safe limits', function (): void {
    $field = JsonField::make('payload')->rootType('object')->maxBytes(100)->maxDepth(3);

    expect($field->normalizeValue('{"enabled":true}'))->toBe(['enabled' => true])
        ->and($field->formatForInput(['enabled' => true]))->toContain('"enabled": true')
        ->and($field->formatForInput([]))->toBe('{}')
        ->and(fn () => $field->normalizeValue('[1,2]'))->toThrow(InvalidArgumentException::class)
        ->and($field->getSchema()['x-seviaflow-json'])->toBe([
            'root' => 'object',
            'maxBytes' => 100,
            'maxDepth' => 3,
        ]);
});

it('normalizes color values to six digit hex and rejects unsafe values', function (): void {
    $field = ColorField::make('brand_color');

    expect($field->normalizeValue(' #aBc123 '))->toBe('#ABC123')
        ->and($field->getSchema()['pattern'])->toBe('^#[0-9A-Fa-f]{6}$');

    $validator = Validator::make(['color' => 'red'], ['color' => $field->defaultValidationRules()]);

    expect($validator->fails())->toBeTrue();
});

it('initializes collection and JSON fields without stringifying cast values', function (): void {
    $fields = [
        CheckboxGroupField::make('channels'),
        TagsField::make('tags'),
        KeyValueField::make('metadata'),
        JsonField::make('payload'),
    ];

    $values = (new FormStateInitializer)->initialize($fields, null);

    expect($values)->toBe([
        'channels' => [],
        'tags' => [],
        'metadata' => [],
        'payload' => '',
    ]);
});
