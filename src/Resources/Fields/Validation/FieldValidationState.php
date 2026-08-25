<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Validation;

use Closure;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\Support\FieldContextState;
use SeviaFlow\Core\Resources\ResourceContext;

final class FieldValidationState
{
    private bool $required = false;

    /**
     * @var array<int, mixed>
     */
    private array $rules = [];

    /**
     * @var array<int, mixed>
     */
    private array $createRules = [];

    /**
     * @var array<int, mixed>
     */
    private array $editRules = [];

    private bool $defaultValidation = true;

    public function __construct(
        private readonly string $fieldName
    ) {}

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function isRequired(ResourceContext|string|null $context, FieldContextState $requiredByContext): bool
    {
        if ($context !== null) {
            return $this->required || $requiredByContext->active($context);
        }

        return $this->required;
    }

    public function setDefaultValidation(bool $enabled): void
    {
        $this->defaultValidation = $enabled;
    }

    public function usesDefaultValidation(): bool
    {
        return $this->defaultValidation;
    }

    public function addRules(array|Closure|string $rules): void
    {
        if ($rules instanceof Closure) {
            $this->rules[] = $rules;

            return;
        }

        foreach ($this->normalizeRuleInput($rules) as $rule) {
            $this->rules[] = $rule;
        }
    }

    public function addCreateRules(array|Closure|string $rules): void
    {
        foreach ($this->normalizeRulesInputForStorage($rules) as $rule) {
            $this->createRules[] = $rule;
        }
    }

    public function addEditRules(array|Closure|string $rules): void
    {
        foreach ($this->normalizeRulesInputForStorage($rules) as $rule) {
            $this->editRules[] = $rule;
        }
    }

    /**
     * @param  callable(ResourceContext|string|null):bool  $isRequiredResolver
     * @return array<int, mixed>
     */
    public function rules(ResourceContext|string|null $context, mixed $record, callable $isRequiredResolver): array
    {
        if ($context === null) {
            return $this->deduplicateRules($this->resolveRules($this->rules, $record, null, false, $isRequiredResolver));
        }

        return $this->rulesForContext($context, $record, $isRequiredResolver);
    }

    /**
     * @param  callable(ResourceContext|string|null):bool  $isRequiredResolver
     * @return array<int, mixed>
     */
    public function rulesForContext(ResourceContext|string $context, mixed $record, callable $isRequiredResolver): array
    {
        $resolved = $this->resolveRules($this->rules, $record, $context, true, $isRequiredResolver);
        $normalizedContext = $this->normalizeContext($context);

        if ($normalizedContext === ResourceContext::Create) {
            foreach ($this->resolveRules($this->createRules, $record, $context, false, $isRequiredResolver) as $rule) {
                $resolved[] = $rule;
            }
        }

        if ($normalizedContext === ResourceContext::Edit) {
            foreach ($this->resolveRules($this->editRules, $record, $context, false, $isRequiredResolver) as $rule) {
                $resolved[] = $rule;
            }
        }

        return $this->deduplicateRules($resolved);
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeRuleInput(mixed $rules): array
    {
        if ($rules === null || $rules === '') {
            return [];
        }

        if (is_string($rules)) {
            return array_values(array_filter(
                array_map(static fn (string $rule): string => trim($rule), explode('|', $rules)),
                static fn (string $rule): bool => $rule !== ''
            ));
        }

        if (is_array($rules)) {
            $normalized = [];
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    foreach ($this->normalizeRuleInput($rule) as $stringRule) {
                        $normalized[] = $stringRule;
                    }

                    continue;
                }

                if ($rule === null) {
                    continue;
                }

                $normalized[] = $rule;
            }

            return $normalized;
        }

        if ($rules instanceof Closure || is_object($rules)) {
            return [$rules];
        }

        throw new InvalidArgumentException(sprintf(
            'Field [%s] rules must be a string, array, closure, or rule object. [%s] given.',
            $this->fieldName,
            get_debug_type($rules)
        ));
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, mixed>
     */
    private function deduplicateRules(array $rules): array
    {
        $stringSeen = [];
        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $key = strtolower(trim($rule));
                if ($key === '') {
                    continue;
                }

                if (isset($stringSeen[$key])) {
                    continue;
                }

                $stringSeen[$key] = true;
                $normalized[] = $rule;

                continue;
            }

            $normalized[] = $rule;
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  callable(ResourceContext|string|null):bool  $isRequiredResolver
     * @return array<int, mixed>
     */
    private function resolveRules(
        array $rules,
        mixed $record,
        ResourceContext|string|null $context,
        bool $includeRequired,
        callable $isRequiredResolver
    ): array {
        $resolved = [];

        if ($includeRequired && $isRequiredResolver($context)) {
            $resolved[] = 'required';
        }

        foreach ($rules as $rule) {
            if ($rule instanceof Closure) {
                foreach ($this->normalizeRuleInput($rule($record, $context)) as $resolvedRule) {
                    $resolved[] = $resolvedRule;
                }

                continue;
            }

            $resolved[] = $rule;
        }

        return $resolved;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeRulesInputForStorage(array|Closure|string $rules): array
    {
        if ($rules instanceof Closure) {
            return [$rules];
        }

        return $this->normalizeRuleInput($rules);
    }

    private function normalizeContext(ResourceContext|string $context): ResourceContext
    {
        if ($context instanceof ResourceContext) {
            return $context;
        }

        return ResourceContext::from(strtolower(trim($context)));
    }
}
