<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ResourceContext;
use Tests\Fixtures\Fields\DummyField;

it('stores name', function (): void {
    $field = DummyField::make('email');

    expect($field->name())->toBe('email');
});

it('uses human-readable label by default', function (): void {
    expect(DummyField::make('first_name')->getLabel())->toBe('First name')
        ->and(DummyField::make('created_at')->getLabel())->toBe('Created at');
});

it('allows explicit label override', function (): void {
    $field = DummyField::make('name')->label('Full name');

    expect($field->getLabel())->toBe('Full name');
});

it('handles required flag', function (): void {
    $field = DummyField::make('name');

    expect($field->isRequired())->toBeFalse();

    $field->required();
    expect($field->isRequired())->toBeTrue();

    $field->required(false);
    expect($field->isRequired())->toBeFalse();
});

it('supports requiredOnCreate only for create context', function (): void {
    $field = DummyField::make('name')->requiredOnCreate();

    expect($field->isRequired('create'))->toBeTrue()
        ->and($field->isRequired('edit'))->toBeFalse()
        ->and($field->isRequired('show'))->toBeFalse();
});

it('supports requiredOnEdit only for edit context', function (): void {
    $field = DummyField::make('name')->requiredOnEdit();

    expect($field->isRequired('edit'))->toBeTrue()
        ->and($field->isRequired('create'))->toBeFalse();
});

it('handles help text', function (): void {
    $field = DummyField::make('name');

    expect($field->getHelp())->toBeNull();

    $field->help('Shown to operators');
    expect($field->getHelp())->toBe('Shown to operators');

    $field->help(null);
    expect($field->getHelp())->toBeNull();
});

it('handles placeholder text', function (): void {
    $field = DummyField::make('name');

    expect($field->getPlaceholder())->toBeNull();

    $field->placeholder('Enter value');
    expect($field->getPlaceholder())->toBe('Enter value');

    $field->placeholder(null);
    expect($field->getPlaceholder())->toBeNull();
});

it('handles disabled flag', function (): void {
    $field = DummyField::make('name');

    expect($field->isDisabled())->toBeFalse();

    $field->disabled();
    expect($field->isDisabled())->toBeTrue();

    $field->disabled(false);
    expect($field->isDisabled())->toBeFalse();
});

it('supports disabledOnEdit only for edit context', function (): void {
    $field = DummyField::make('name')->disabledOnEdit();

    expect($field->isDisabled('edit'))->toBeTrue()
        ->and($field->isDisabled('create'))->toBeFalse()
        ->and($field->isDisabled('show'))->toBeFalse();
});

it('handles readonly flag', function (): void {
    $field = DummyField::make('name');

    expect($field->isReadonly())->toBeFalse();

    $field->readonly();
    expect($field->isReadonly())->toBeTrue();

    $field->readonly(false);
    expect($field->isReadonly())->toBeFalse();
});

it('supports readonlyOnEdit only for edit context', function (): void {
    $field = DummyField::make('name')->readonlyOnEdit();

    expect($field->isReadonly('edit'))->toBeTrue()
        ->and($field->isReadonly('create'))->toBeFalse()
        ->and($field->isReadonly('show'))->toBeFalse();
});

it('supports multiple contexts for state rules', function (): void {
    $field = DummyField::make('name')
        ->requiredOn(['create', 'edit'])
        ->readonlyOn(['edit', 'show'])
        ->disabledOn([ResourceContext::Show]);

    expect($field->isRequired('create'))->toBeTrue()
        ->and($field->isRequired('edit'))->toBeTrue()
        ->and($field->isRequired('show'))->toBeFalse()
        ->and($field->isReadonly('edit'))->toBeTrue()
        ->and($field->isReadonly('show'))->toBeTrue()
        ->and($field->isReadonly('create'))->toBeFalse()
        ->and($field->isDisabled('show'))->toBeTrue()
        ->and($field->isDisabled('edit'))->toBeFalse();
});

it('is visible by default on create edit and show contexts', function (): void {
    $field = DummyField::make('name');

    expect($field->isVisible(ResourceContext::Create))->toBeTrue()
        ->and($field->isVisible(ResourceContext::Edit))->toBeTrue()
        ->and($field->isVisible(ResourceContext::Show))->toBeTrue();
});

it('hides field on create context', function (): void {
    $field = DummyField::make('name')->hiddenOnCreate();

    expect($field->isHidden('create'))->toBeTrue()
        ->and($field->isVisible('edit'))->toBeTrue()
        ->and($field->isVisible('show'))->toBeTrue();
});

it('hides field on edit context', function (): void {
    $field = DummyField::make('name')->hiddenOnEdit();

    expect($field->isHidden(ResourceContext::Edit))->toBeTrue()
        ->and($field->isVisible(ResourceContext::Create))->toBeTrue();
});

it('limits visibility to provided contexts', function (): void {
    $field = DummyField::make('name')->visibleOn(['create', ResourceContext::Show]);

    expect($field->isVisible('create'))->toBeTrue()
        ->and($field->isVisible('show'))->toBeTrue()
        ->and($field->isHidden('edit'))->toBeTrue();
});

it('accepts multiple contexts for hiddenOn', function (): void {
    $field = DummyField::make('name')->hiddenOn(['create', 'edit']);

    expect($field->isHidden('create'))->toBeTrue()
        ->and($field->isHidden('edit'))->toBeTrue()
        ->and($field->isVisible('show'))->toBeTrue();
});

it('supports visibility convenience methods', function (): void {
    $field = DummyField::make('name')
        ->visibleOn('show')
        ->visibleOnCreate()
        ->hiddenOnCreate()
        ->visibleOnEdit()
        ->hiddenOnShow();

    expect($field->isVisible('edit'))->toBeTrue()
        ->and($field->isHidden('create'))->toBeTrue()
        ->and($field->isHidden('show'))->toBeTrue();
});

it('rejects invalid context values clearly', function (): void {
    $field = DummyField::make('name');

    expect(fn () => $field->visibleOn('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Invalid resource context');
});

it('rejects invalid context in state methods clearly', function (): void {
    $field = DummyField::make('name');

    expect(fn () => $field->requiredOn('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Invalid resource context')
        ->and(fn () => $field->readonlyOn('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Invalid resource context')
        ->and(fn () => $field->disabledOn('invalid'))
        ->toThrow(InvalidArgumentException::class, 'Invalid resource context');
});

it('visibility rules do not affect required and readonly flags', function (): void {
    $field = DummyField::make('name')
        ->required()
        ->readonly()
        ->hiddenOnCreate();

    expect($field->isRequired())->toBeTrue()
        ->and($field->isReadonly())->toBeTrue()
        ->and($field->isHidden('create'))->toBeTrue();
});

it('stores array rules', function (): void {
    $field = DummyField::make('name')->rules(['string', 'max:255']);

    expect($field->getRules())->toBe(['string', 'max:255']);
});

it('stores pipe string rules', function (): void {
    $field = DummyField::make('name')->rules('string|max:255');

    expect($field->getRules())->toBe(['string', 'max:255']);
});

it('supports closure rules with context and record passthrough', function (): void {
    $record = (object) ['type' => 'person'];
    $field = DummyField::make('name')->rules(
        fn (mixed $passedRecord, ResourceContext|string|null $context): array => [
            $passedRecord === $record ? 'string' : 'inconsistent',
            $context === ResourceContext::Create ? 'max:255' : 'max:100',
        ]
    );

    expect($field->getRules(ResourceContext::Create, $record))->toBe(['string', 'max:255'])
        ->and($field->getRules(ResourceContext::Edit, $record))->toBe(['string', 'max:100']);
});

it('merges required with custom rules and avoids duplicate required', function (): void {
    $field = DummyField::make('name')
        ->required()
        ->rules(['required', 'string']);

    expect($field->getRules())->toBe(['required', 'string']);
});

it('returns empty rules by default when not required', function (): void {
    $field = DummyField::make('name');

    expect($field->getRules())->toBe([]);
});

it('can disable and re-enable default validation flag', function (): void {
    $field = DummyField::make('name');

    expect($field->usesDefaultValidation())->toBeTrue();

    $field->withoutDefaultValidation();
    expect($field->usesDefaultValidation())->toBeFalse();

    $field->withDefaultValidation();
    expect($field->usesDefaultValidation())->toBeTrue();
});

it('applies create rules only on create context', function (): void {
    $field = DummyField::make('name')
        ->rules('string')
        ->rulesOnCreate('max:255');

    expect($field->getRulesForContext(ResourceContext::Create))->toBe(['string', 'max:255'])
        ->and($field->getRulesForContext(ResourceContext::Edit))->toBe(['string']);
});

it('applies edit rules only on edit context', function (): void {
    $field = DummyField::make('name')
        ->rules('string')
        ->rulesOnEdit('min:3');

    expect($field->getRulesForContext(ResourceContext::Create))->toBe(['string'])
        ->and($field->getRulesForContext(ResourceContext::Edit))->toBe(['string', 'min:3']);
});

it('supports rulesOnUpdate alias for edit context', function (): void {
    $field = DummyField::make('name')
        ->rules('string')
        ->rulesOnUpdate('max:120');

    expect($field->getRulesForContext(ResourceContext::Edit))->toBe(['string', 'max:120'])
        ->and($field->getRulesForContext(ResourceContext::Create))->toBe(['string']);
});
