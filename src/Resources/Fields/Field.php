<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\Support\FieldContextState;
use SeviaFlow\Core\Resources\Fields\Support\FieldVisibilityRules;
use SeviaFlow\Core\Resources\Fields\Validation\FieldValidationState;
use SeviaFlow\Core\Resources\ResourceContext;

abstract class Field
{
    protected static string $type = 'text';

    /**
     * @var array<string, mixed>
     */
    private array $apiSchema = [];

    private ?string $label = null;

    private ?string $help = null;

    private ?string $placeholder = null;

    private mixed $defaultValue = null;

    private bool $hasDefaultValue = false;

    private ?int $columnSpan = null;

    /**
     * @var array<int, array{fields: array<int, string>, condition: Closure, type: string}>
     */
    private array $conditionalStates = [];

    private bool $disabled = false;

    private bool $readonly = false;

    private FieldContextState $requiredByContext;

    private FieldContextState $disabledByContext;

    private FieldContextState $readonlyByContext;

    private FieldVisibilityRules $visibility;

    private FieldValidationState $validationState;

    final protected function __construct(
        private readonly string $name
    ) {
        $this->requiredByContext = new FieldContextState;
        $this->disabledByContext = new FieldContextState;
        $this->readonlyByContext = new FieldContextState;
        $this->visibility = new FieldVisibilityRules;
        $this->validationState = new FieldValidationState($name);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return static::$type;
    }

    public function getType(): string
    {
        return $this->type();
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function schema(array $schema): static
    {
        $this->apiSchema = array_replace_recursive($this->apiSchema, $schema);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return array_replace_recursive($this->defaultApiSchema(), $this->apiSchema);
    }

    /**
     * @return array<string, mixed>
     */
    public function getApiSchema(): array
    {
        return $this->getSchema();
    }

    public function nullable(bool $condition = true): static
    {
        return $this->schema(['nullable' => $condition]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'nullable' => true,
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        return $this->normalizeStringValue($value);
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;

        return $this;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefaultValue;
    }

    public function getDefaultValue(FormState $state, ?Model $record = null): mixed
    {
        if (! $this->hasDefaultValue) {
            return null;
        }

        return $this->defaultValue instanceof Closure
            ? ($this->defaultValue)($state, $record)
            : $this->defaultValue;
    }

    public function columnSpan(int|string $span): static
    {
        if ($span === 'full') {
            $this->columnSpan = 12;

            return $this;
        }

        if (! is_int($span) && (! is_string($span) || ! ctype_digit($span))) {
            throw new \InvalidArgumentException('Field column span must be an integer or "full".');
        }

        $normalized = (int) $span;
        if ($normalized < 1 || $normalized > 12) {
            throw new \InvalidArgumentException('Field column span must be between 1 and 12.');
        }

        $this->columnSpan = $normalized;

        return $this;
    }

    public function getColumnSpan(): ?int
    {
        return $this->columnSpan;
    }

    public function prepareForValidation(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function formatForInput(mixed $value): mixed
    {
        return $value;
    }

    protected function normalizeStringValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    public function label(?string $label = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return Str::of($this->name)
            ->replace('_', ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    public function required(bool $condition = true): static
    {
        $this->validationState->setRequired($condition);

        return $this;
    }

    public function isRequired(ResourceContext|string|null $context = null): bool
    {
        return $this->validationState->isRequired($context, $this->requiredByContext);
    }

    public function help(?string $text = null): static
    {
        $this->help = $text;

        return $this;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function placeholder(?string $text = null): static
    {
        $this->placeholder = $text;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function disabled(bool $condition = true): static
    {
        $this->disabled = $condition;

        return $this;
    }

    public function isDisabled(ResourceContext|string|null $context = null): bool
    {
        if ($context !== null) {
            return $this->disabled || $this->disabledByContext->active($context);
        }

        return $this->disabled;
    }

    public function readonly(bool $condition = true): static
    {
        $this->readonly = $condition;

        return $this;
    }

    public function isReadonly(ResourceContext|string|null $context = null): bool
    {
        if ($context !== null) {
            return $this->readonly || $this->readonlyByContext->active($context);
        }

        return $this->readonly;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function requiredOn(ResourceContext|array|string $contexts): static
    {
        $this->requiredByContext->set($contexts, true);

        return $this;
    }

    public function requiredOnCreate(): static
    {
        $this->requiredByContext->setForContext(ResourceContext::Create, true);

        return $this;
    }

    public function requiredOnEdit(): static
    {
        $this->requiredByContext->setForContext(ResourceContext::Edit, true);

        return $this;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function readonlyOn(ResourceContext|array|string $contexts): static
    {
        $this->readonlyByContext->set($contexts, true);

        return $this;
    }

    public function readonlyOnCreate(): static
    {
        $this->readonlyByContext->setForContext(ResourceContext::Create, true);

        return $this;
    }

    public function readonlyOnEdit(): static
    {
        $this->readonlyByContext->setForContext(ResourceContext::Edit, true);

        return $this;
    }

    public function readonlyOnShow(): static
    {
        $this->readonlyByContext->setForContext(ResourceContext::Show, true);

        return $this;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function disabledOn(ResourceContext|array|string $contexts): static
    {
        $this->disabledByContext->set($contexts, true);

        return $this;
    }

    public function disabledOnCreate(): static
    {
        $this->disabledByContext->setForContext(ResourceContext::Create, true);

        return $this;
    }

    public function disabledOnEdit(): static
    {
        $this->disabledByContext->setForContext(ResourceContext::Edit, true);

        return $this;
    }

    public function disabledOnShow(): static
    {
        $this->disabledByContext->setForContext(ResourceContext::Show, true);

        return $this;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function visibleOn(ResourceContext|array|string $contexts): static
    {
        $this->visibility->visibleOn($contexts);

        return $this;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function hiddenOn(ResourceContext|array|string $contexts): static
    {
        $this->visibility->hiddenOn($contexts);

        return $this;
    }

    public function visibleOnCreate(): static
    {
        $this->visibility->setForContext(ResourceContext::Create, true);

        return $this;
    }

    public function visibleOnEdit(): static
    {
        $this->visibility->setForContext(ResourceContext::Edit, true);

        return $this;
    }

    public function visibleOnShow(): static
    {
        $this->visibility->setForContext(ResourceContext::Show, true);

        return $this;
    }

    public function hiddenOnCreate(): static
    {
        $this->visibility->setForContext(ResourceContext::Create, false);

        return $this;
    }

    public function hiddenOnEdit(): static
    {
        $this->visibility->setForContext(ResourceContext::Edit, false);

        return $this;
    }

    public function hiddenOnShow(): static
    {
        $this->visibility->setForContext(ResourceContext::Show, false);

        return $this;
    }

    public function isVisible(ResourceContext|string $context): bool
    {
        return $this->visibility->isVisible($context);
    }

    public function isHidden(ResourceContext|string $context): bool
    {
        return ! $this->isVisible($context);
    }

    public function hiddenWhen(string|array $fields, callable $condition): static
    {
        $this->addConditionalState($fields, $condition, 'hidden');

        return $this;
    }

    public function requiredWhen(string|array $fields, callable $condition): static
    {
        $this->addConditionalState($fields, $condition, 'required');

        return $this;
    }

    public function disabledWhen(string|array $fields, callable $condition): static
    {
        $this->addConditionalState($fields, $condition, 'disabled');

        return $this;
    }

    public function isVisibleForState(ResourceContext $context, ?FormState $state = null): bool
    {
        if (! $this->isVisible($context) || $state === null) {
            return $this->isVisible($context);
        }

        return ! $this->conditionalStateIsActive('hidden', $state);
    }

    public function isRequiredForState(ResourceContext $context, ?FormState $state = null): bool
    {
        if ($this->isRequired($context)) {
            return true;
        }

        return $state !== null && $this->conditionalStateIsActive('required', $state);
    }

    public function isDisabledForState(ResourceContext $context, ?FormState $state = null): bool
    {
        if ($this->isDisabled($context)) {
            return true;
        }

        return $state !== null && $this->conditionalStateIsActive('disabled', $state);
    }

    /**
     * @return array<int, string>
     */
    public function getDependencyFields(): array
    {
        $dependencies = [];

        foreach ($this->conditionalStates as $conditionalState) {
            $dependencies = array_merge($dependencies, $conditionalState['fields']);
        }

        return array_values(array_unique($dependencies));
    }

    public function withoutDefaultValidation(bool $condition = true): static
    {
        if ($condition) {
            $this->validationState->setDefaultValidation(false);
        }

        return $this;
    }

    public function withDefaultValidation(bool $condition = true): static
    {
        if ($condition) {
            $this->validationState->setDefaultValidation(true);
        }

        return $this;
    }

    public function usesDefaultValidation(): bool
    {
        return $this->validationState->usesDefaultValidation();
    }

    public function rules(array|Closure|string $rules): static
    {
        $this->validationState->addRules($rules);

        return $this;
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, mixed>  $fieldValues
     * @return array<int, mixed>
     */
    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [];
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, array<int, mixed>>
     */
    public function defaultNestedValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [];
    }

    public function minLength(int $length): static
    {
        return $this->rules('min:'.$length);
    }

    public function maxLength(int $length): static
    {
        return $this->rules('max:'.$length);
    }

    public function length(int $length): static
    {
        return $this->rules('size:'.$length);
    }

    public function regex(string $pattern): static
    {
        return $this->rules('regex:'.$pattern);
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(ResourceContext|string|null $context = null, mixed $record = null): array
    {
        return $this->validationState->rules(
            $context,
            $record,
            fn (ResourceContext|string|null $resolvedContext): bool => $this->isRequired($resolvedContext)
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function getRulesForContext(ResourceContext|string $context, mixed $record = null): array
    {
        return $this->validationState->rulesForContext(
            $context,
            $record,
            fn (ResourceContext|string|null $resolvedContext): bool => $this->isRequired($resolvedContext)
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function getRulesForState(ResourceContext $context, mixed $record = null, ?FormState $state = null): array
    {
        $rules = $this->getRulesForContext($context, $record);

        if ($state !== null && $this->isRequiredForState($context, $state) && ! in_array('required', $rules, true)) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    public function rulesOnCreate(array|Closure|string $rules): static
    {
        $this->validationState->addCreateRules($rules);

        return $this;
    }

    public function rulesOnEdit(array|Closure|string $rules): static
    {
        $this->validationState->addEditRules($rules);

        return $this;
    }

    public function rulesOnUpdate(array|Closure|string $rules): static
    {
        return $this->rulesOnEdit($rules);
    }

    private function addConditionalState(string|array $fields, callable $condition, string $type): void
    {
        $normalizedFields = array_values(array_unique(array_filter(
            is_array($fields) ? $fields : [$fields],
            static fn (mixed $field): bool => is_string($field) && trim($field) !== ''
        )));

        if ($normalizedFields === []) {
            throw new \InvalidArgumentException('Conditional field state requires at least one dependency.');
        }

        $this->conditionalStates[] = [
            'fields' => $normalizedFields,
            'condition' => Closure::fromCallable($condition),
            'type' => $type,
        ];
    }

    private function conditionalStateIsActive(string $type, FormState $state): bool
    {
        foreach ($this->conditionalStates as $conditionalState) {
            if ($conditionalState['type'] !== $type) {
                continue;
            }

            if ((bool) ($conditionalState['condition'])($state)) {
                return true;
            }
        }

        return false;
    }
}
