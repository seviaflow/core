<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TextareaField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\ResourceContext;

function repeaterTestField(): RepeaterField
{
    return RepeaterField::make('items')
        ->fields([
            TextField::make('label')->required(),
            NumberField::make('quantity')->min(1),
            MoneyField::make('amount')->scale(2),
            SelectField::make('kind')->options(['service' => 'Service', 'product' => 'Product']),
            ToggleField::make('enabled'),
        ])
        ->itemLabel('label')
        ->minItems(1)
        ->maxItems(3)
        ->maxBytes(4096);
}

it('roundtrips one-level JSON objects without persisting UI keys', function (): void {
    $field = repeaterTestField();
    $prepared = $field->prepareForValidation('[{"label":"  Polish service  ","quantity":"2","amount":"9.50","kind":"service","enabled":"false"}]');

    expect($prepared)->toBe([[
        'label' => 'Polish service',
        'quantity' => '2',
        'amount' => '9.50',
        'kind' => 'service',
        'enabled' => false,
    ]])
        ->and($field->normalizeValue($prepared))->toBe([[
            'label' => 'Polish service',
            'quantity' => 2,
            'amount' => '9.50',
            'kind' => 'service',
            'enabled' => false,
        ]])
        ->and($field->getSchema()['items']['additionalProperties'])->toBeFalse()
        ->and($field->getSchema()['minItems'])->toBe(1)
        ->and($field->getSchema()['maxItems'])->toBe(3)
        ->and($field->getSchema()['x-seviaflow-repeater']['depth'])->toBe(1);
});

it('validates nested values and rejects unknown or nested payload keys', function (): void {
    $field = repeaterTestField();
    $rules = (new FormValidationRules)->build([$field], ResourceContext::Create);
    $validator = Validator::make([
        'fieldValues' => [
            'items' => [[
                'label' => '',
                'quantity' => 'not-a-number',
                'amount' => '9.999',
                'kind' => 'unknown',
                'enabled' => 'not-a-boolean',
                '_ui_key' => 'should-never-persist',
            ]],
        ],
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('fieldValues.items'))->toBeTrue()
        ->and($validator->errors()->has('fieldValues.items.0.label'))->toBeTrue()
        ->and($validator->errors()->has('fieldValues.items.0.quantity'))->toBeTrue()
        ->and($validator->errors()->has('fieldValues.items.0.kind'))->toBeTrue();

    expect(fn () => $field->normalizeValue([['label' => 'x', 'nested' => ['bad']]]))
        ->toThrow(InvalidArgumentException::class, 'unknown field');
});

it('handles empty state and rejects limits or non-object rows', function (): void {
    $field = repeaterTestField();

    expect($field->normalizeValue(null))->toBe([])
        ->and((new FormStateInitializer)->initialize([$field], null))->toBe(['items' => []]);

    expect(fn () => $field->normalizeValue([
        ['label' => 'a', 'quantity' => 1, 'kind' => 'service'],
        ['label' => 'b', 'quantity' => 1, 'kind' => 'service'],
        ['label' => 'c', 'quantity' => 1, 'kind' => 'service'],
        ['label' => 'd', 'quantity' => 1, 'kind' => 'service'],
    ]))->toThrow(InvalidArgumentException::class, 'more than 3')
        ->and(fn () => $field->normalizeValue([['label', 'not-an-object']]))
        ->toThrow(InvalidArgumentException::class, 'must be an object');
});

it('rejects unsupported nested field types at definition time', function (): void {
    expect(fn () => RepeaterField::make('items')->fields([
        RepeaterField::make('nested'),
    ]))->toThrow(InvalidArgumentException::class, 'unsupported type');
});

it('validates new scalar types and dependent options separately for each row', function (): void {
    $field = RepeaterField::make('contacts')->fields([
        TextareaField::make('notes'),
        DateField::make('date')->required(),
        EmailField::make('email')->required(),
        SelectField::make('country')->options(['PL' => 'Poland', 'NO' => 'Norway']),
        SelectField::make('city')->dependsOn('country')->options(static fn (FormState $state): array => $state->value('country') === 'PL' ? ['byd' => 'Bydgoszcz'] : ['osl' => 'Oslo']
        ),
    ]);
    $values = ['contacts' => [
        ['notes' => '  Notes  ', 'date' => '2026-10-12', 'email' => 'a@example.com', 'country' => 'PL', 'city' => 'byd'],
        ['date' => '2026-10-13', 'email' => 'b@example.com', 'country' => 'NO', 'city' => 'osl'],
    ]];
    $rules = (new FormValidationRules)->build([$field], ResourceContext::Create, fieldValues: $values);
    expect(Validator::make(['fieldValues' => $values], $rules)->passes())->toBeTrue()
        ->and($field->normalizeValue($values['contacts'])[0]['notes'])->toBe('Notes');
    $values['contacts'][0]['city'] = 'osl';
    $values['contacts'][1]['email'] = 'invalid';
    $rules = (new FormValidationRules)->build([$field], ResourceContext::Create, fieldValues: $values);
    $validator = Validator::make(['fieldValues' => $values], $rules);
    expect($validator->errors()->has('fieldValues.contacts.0.city'))->toBeTrue()
        ->and($validator->errors()->has('fieldValues.contacts.1.email'))->toBeTrue();
});
