<?php

declare(strict_types=1);

use SeviaFlow\Core\Forms\FieldValueNormalizer;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\ToggleField;

it('exposes a small type schema and normalization contract', function (): void {
    $field = ContractField::make('tracking_code')->schema([
        'type' => 'string',
        'pattern' => '^[A-Z]+$',
    ]);

    expect($field->type())->toBe('tracking-code')
        ->and($field->getSchema())->toMatchArray([
            'type' => 'string',
            'pattern' => '^[A-Z]+$',
        ])
        ->and((new FieldValueNormalizer)->normalize($field, '  ABC  '))->toBe('ABC');
});

it('keeps declared schemas aligned with existing field values', function (): void {
    $money = MoneyField::make('total')->currency('PLN')->scale(3);
    $multiSelect = MultiSelectField::make('channels')->options(['email' => 'Email']);

    expect($money->getSchema())->toMatchArray([
        'type' => 'string',
        'format' => 'decimal',
        'x-seviaflow-money' => ['currency' => 'PLN', 'scale' => 3],
    ])
        ->and($multiSelect->getSchema())->toMatchArray([
            'type' => 'array',
            'items' => ['type' => 'string', 'enum' => ['email']],
        ])
        ->and((new FieldValueNormalizer)->normalize($money, ' 12.500 '))->toBe('12.500')
        ->and((new FieldValueNormalizer)->normalize($multiSelect, ['email', 'email', '']))->toBe(['email'])
        ->and((new FieldValueNormalizer)->normalize(ToggleField::make('enabled'), 'false'))->toBeFalse();
});

final class ContractField extends Field
{
    protected static string $type = 'tracking-code';
}
