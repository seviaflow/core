<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\ResourceContext;

enum SelectTestState: string
{
    case Draft = 'draft';
    case Published = 'published';
}

it('supports enum options and grouped values without losing integer keys', function (): void {
    expect(SelectField::make('status')->options(SelectTestState::class)->getOptions())
        ->toBe(['draft' => 'Draft', 'published' => 'Published']);
    $field = MultiSelectField::make('ids')->options(['First' => [1 => 'One', 2 => 'Two'], 'Second' => [3 => 'Three']]);
    expect($field->getOptions())->toBe([1 => 'One', 2 => 'Two', 3 => 'Three'])
        ->and($field->getOptionGroups())->toHaveKeys(['First', 'Second']);
});

it('validates dependent choices on the server and rejects disabled choices', function (): void {
    $field = SelectField::make('city')->dependsOn('country')->options(static fn (FormState $state): array => $state->value('country') === 'PL' ? ['byd' => 'Bydgoszcz', 'war' => 'Warszawa'] : ['osl' => 'Oslo']
    )->disabledOptions(['war'])->searchable();
    $rules = (new FormValidationRules)->build([$field], ResourceContext::Create, fieldValues: ['country' => 'PL']);
    expect(Validator::make(['fieldValues' => ['city' => 'byd']], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['fieldValues' => ['city' => 'osl']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['fieldValues' => ['city' => 'war']], $rules)->fails())->toBeTrue()
        ->and($field->getDependencyFields())->toBe(['country'])
        ->and($field->getSchema())->not->toHaveKey('enum');
});

it('rejects duplicate values across option groups', function (): void {
    expect(fn () => SelectField::make('kind')->options(['A' => ['x' => 'One'], 'B' => ['x' => 'Two']])->getOptions())
        ->toThrow(InvalidArgumentException::class);
});
