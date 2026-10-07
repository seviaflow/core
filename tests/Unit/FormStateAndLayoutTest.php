<?php

declare(strict_types=1);

use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Section;
use SeviaFlow\Core\Resources\Tab;

it('keeps flat fields while exposing additive grid and tab layout', function (): void {
    $name = TextField::make('name')->columnSpan('full');
    $tab = Tab::make('Details')->key('details')->fields([$name]);
    $section = Section::make('More')->columnSpan(6)->collapsible()->collapsed();

    $form = (new Form)->columns(3)->fields([$name])->sections([$section])->tabs([$tab]);

    expect($form->getColumns())->toBe(3)
        ->and($form->getFields())->toHaveCount(1)
        ->and($form->getSections())->toHaveCount(1)
        ->and($form->getTabs()[0])->toBe($tab)
        ->and($form->getAllFields())->toHaveCount(2)
        ->and($name->getColumnSpan())->toBe(12)
        ->and($section->getColumnSpan())->toBe(6)
        ->and($section->isCollapsible())->toBeTrue()
        ->and($section->isCollapsed())->toBeTrue();
});

it('initializes defaults and evaluates conditional state from current values', function (): void {
    $type = SelectField::make('type')->default('company')->options(['person' => 'Person', 'company' => 'Company']);
    $taxId = TextField::make('tax_id')
        ->requiredWhen('type', static fn (FormState $state): bool => $state->value('type') === 'company')
        ->hiddenWhen('type', static fn (FormState $state): bool => $state->value('type') === 'person');
    $fields = [$type, $taxId];
    $initializer = new FormStateInitializer;
    $values = $initializer->initialize($fields, null);
    $presence = $initializer->initialPresence($fields, null);
    $state = new FormState($values, ResourceContext::Create);

    expect($values['type'])->toBe('company')
        ->and($presence)->toBe(['type' => true])
        ->and($taxId->isRequiredForState(ResourceContext::Create, $state))->toBeTrue()
        ->and($taxId->isVisibleForState(ResourceContext::Create, $state))->toBeTrue()
        ->and((new FormValidationRules)->build($fields, ResourceContext::Create, null, 'fieldValues', $values, null, $state)['fieldValues.tax_id'])
        ->toContain('required');

    $personState = $state->withValues(['type' => 'person', 'tax_id' => 'kept']);
    expect($taxId->isVisibleForState(ResourceContext::Create, $personState))->toBeFalse()
        ->and((new FormFields)->persistable($fields, ResourceContext::Create, $personState))
        ->not->toContain($taxId);
});

it('rejects cyclic conditional dependencies before rendering state', function (): void {
    $first = TextField::make('first')->hiddenWhen('second', static fn (): bool => true);
    $second = TextField::make('second')->hiddenWhen('first', static fn (): bool => true);

    expect(fn () => (new FormStateInitializer)->initialize([$first, $second], null))
        ->toThrow(LogicException::class, 'Cyclic form dependency detected');
});

it('supports number precision and timezone-aware input formats', function (): void {
    $number = NumberField::make('amount')->precision(2);
    $dateTime = DateTimeField::make('starts_at')->timezone('Europe/Warsaw');

    expect($number->getStep())->toBe(0.01)
        ->and($number->defaultValidationRules())->toContain('decimal:0,2')
        ->and($dateTime->formatForInput('2026-03-29T00:30:00+00:00'))->toBe('2026-03-29T01:30');
});
