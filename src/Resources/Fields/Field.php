<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SeviaFlow\Core\Resources\Fields\Support\FieldContextState;
use SeviaFlow\Core\Resources\Fields\Support\FieldVisibilityRules;
use SeviaFlow\Core\Resources\Fields\Validation\FieldValidationState;
use SeviaFlow\Core\Resources\ResourceContext;

abstract class Field
{
    private ?string $label = null;

    private ?string $help = null;

    private ?string $placeholder = null;

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
}
