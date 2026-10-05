<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\ResourceContext;

final class FormValidationRules
{
    public function __construct(
        private readonly FormFields $formFields = new FormFields
    ) {}

    /**
     * @param  array<int, Field>  $fields
     * @param  class-string<Model>|null  $modelClass
     * @param  non-empty-string  $rootKey
     * @return array<string, array<int, mixed>>
     */
    public function build(
        array $fields,
        ResourceContext $context,
        ?string $modelClass = null,
        string $rootKey = 'fieldValues',
        array $fieldValues = [],
        ?Model $record = null,
        ?FormState $state = null
    ): array {
        if ($context === ResourceContext::Show) {
            return [];
        }

        $rules = [];
        foreach ($this->formFields->persistable($fields, $context, $state) as $field) {
            $fieldRules = $field->getRulesForState($context, $record, $state);

            if (! $this->containsRule($fieldRules, 'required') && ! $this->containsRule($fieldRules, 'nullable')) {
                $fieldRules[] = 'nullable';
            }

            if ($field->usesDefaultValidation()) {
                foreach ($field->defaultValidationRules($context, $record, $modelClass, $fieldValues) as $defaultRule) {
                    $this->appendRuleIfMissing($fieldRules, $defaultRule);
                }

                foreach ($field->defaultNestedValidationRules($context, $record, $modelClass, $fieldValues) as $suffix => $nestedRules) {
                    $nestedKey = $rootKey.'.'.$field->name().'.'.$suffix;
                    $rules[$nestedKey] = $this->appendRulesToExisting(
                        $rules[$nestedKey] ?? [],
                        $nestedRules
                    );
                }
            }

            $rules[$rootKey.'.'.$field->name()] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  non-empty-string  $rootKey
     * @return array<string, string>
     */
    public function attributes(
        array $fields,
        ResourceContext $context,
        string $rootKey = 'fieldValues',
        ?FormState $state = null
    ): array {
        if ($context === ResourceContext::Show) {
            return [];
        }

        $attributes = [];

        foreach ($this->formFields->persistable($fields, $context, $state) as $field) {
            $attributes[$rootKey.'.'.$field->name()] = strtolower($field->getLabel());
        }

        return $attributes;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function containsRule(array $rules, string $needle): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && strtolower($rule) === strtolower($needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function appendRuleIfMissing(array &$rules, mixed $rule): void
    {
        if (is_string($rule)) {
            if (! $this->containsRule($rules, $rule)) {
                $rules[] = $rule;
            }

            return;
        }

        if ($rule instanceof In && $this->containsRuleObject($rules, In::class)) {
            return;
        }

        if ($rule instanceof Exists && $this->containsRuleObject($rules, Exists::class)) {
            return;
        }

        $rules[] = $rule;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  class-string  $class
     */
    private function containsRuleObject(array $rules, string $class): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $existing
     * @param  array<int, mixed>  $incoming
     * @return array<int, mixed>
     */
    private function appendRulesToExisting(array $existing, array $incoming): array
    {
        foreach ($incoming as $rule) {
            $this->appendRuleIfMissing($existing, $rule);
        }

        return $existing;
    }
}
