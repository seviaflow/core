<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use SeviaFlow\Core\Resources\Fields\BelongsToManyField;
use SeviaFlow\Core\Resources\Fields\CheckboxGroupField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\JsonField;
use SeviaFlow\Core\Resources\Fields\KeyValueField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\RichTextField;
use SeviaFlow\Core\Resources\Fields\TagsField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\ResourceContext;

final class FormStateInitializer
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initialize(
        array $fields,
        ?Model $record,
        ResourceContext $context = ResourceContext::Create
    ): array {
        $this->assertNoDependencyCycles($fields);
        $fieldValues = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $value = $record?->getAttribute($name);

            if ($field instanceof BelongsToManyField && $record !== null) {
                $fieldValues[$name] = $field->valueForRecord($record);

                continue;
            }
            if ($field instanceof PasswordField) {
                $fieldValues[$name] = '';

                continue;
            }

            if ($field instanceof ToggleField) {
                $fieldValues[$name] = (bool) $value;

                continue;
            }

            if ($field instanceof MultiSelectField || $field instanceof CheckboxGroupField || $field instanceof TagsField) {
                if (is_array($value)) {
                    $fieldValues[$name] = $value;

                    continue;
                }

                $fieldValues[$name] = [];

                continue;
            }

            if ($field instanceof KeyValueField) {
                $fieldValues[$name] = is_array($value) ? $value : [];

                continue;
            }

            if ($field instanceof RepeaterField) {
                $fieldValues[$name] = $field->formatForInput($value);

                continue;
            }

            if ($field instanceof JsonField || $field instanceof RichTextField) {
                $fieldValues[$name] = $value === null ? '' : $field->formatForInput($value);

                continue;
            }

            if ($field instanceof FileField) {
                $fieldValues[$name] = null;

                continue;
            }

            if ($value === null) {
                $fieldValues[$name] = '';

                continue;
            }

            $fieldValues[$name] = (string) $value;
        }

        if ($record === null) {
            $state = new FormState($fieldValues, $context);

            foreach ($fields as $field) {
                if ($field instanceof MultiSelectField || $field instanceof CheckboxGroupField || $field instanceof TagsField) {
                    $fieldValues[$field->name()] ??= [];
                }

                if ($field instanceof KeyValueField) {
                    $fieldValues[$field->name()] ??= [];
                }

                if ($field instanceof RepeaterField) {
                    $fieldValues[$field->name()] ??= [];
                }

                if ($field instanceof JsonField || $field instanceof RichTextField) {
                    $fieldValues[$field->name()] ??= '';
                }
            }

            $state = new FormState($fieldValues, $context);

            foreach ($fields as $field) {
                if (! $field->hasDefault()) {
                    continue;
                }

                $fieldValues[$field->name()] = $field->getDefaultValue($state, null);
                $state = $state->withValues($fieldValues);
            }
        }

        return $fieldValues;
    }

    /**
     * Return the fields that are intentionally present in the initial form state.
     *
     * Empty UI placeholders are not persistence intent. Existing records expose
     * their current fields, while create forms only expose explicit field defaults.
     *
     * @param  array<int, Field>  $fields
     * @return array<string, bool>
     */
    public function initialPresence(array $fields, ?Model $record): array
    {
        $presence = [];

        foreach ($fields as $field) {
            if ($record !== null || $field->hasDefault()) {
                $presence[$field->name()] = true;
            }
        }

        return $presence;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    private function assertNoDependencyCycles(array $fields): void
    {
        $knownFields = array_fill_keys(array_map(
            static fn (Field $field): string => $field->name(),
            $fields
        ), true);
        $graph = [];

        foreach ($fields as $field) {
            $dependencies = $field->getDependencyFields();

            if (method_exists($field, 'getDependsOnField')) {
                $dependsOn = $field->getDependsOnField();
                if (is_string($dependsOn) && $dependsOn !== '') {
                    $dependencies[] = $dependsOn;
                }
            }

            $graph[$field->name()] = array_values(array_filter(
                array_unique($dependencies),
                static fn (string $dependency): bool => isset($knownFields[$dependency])
            ));
        }

        $visited = [];
        $visiting = [];
        foreach (array_keys($graph) as $fieldName) {
            $this->visitDependency($fieldName, $graph, $visited, $visiting, []);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $graph
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     * @param  array<int, string>  $path
     */
    private function visitDependency(
        string $fieldName,
        array $graph,
        array &$visited,
        array &$visiting,
        array $path
    ): void {
        if (isset($visited[$fieldName])) {
            return;
        }

        if (isset($visiting[$fieldName])) {
            $cycleStart = array_search($fieldName, $path, true);
            $cycle = $cycleStart === false
                ? array_merge($path, [$fieldName])
                : array_merge(array_slice($path, $cycleStart), [$fieldName]);

            throw new LogicException('Cyclic form dependency detected: '.implode(' -> ', $cycle));
        }

        $visiting[$fieldName] = true;
        $path[] = $fieldName;

        foreach ($graph[$fieldName] ?? [] as $dependency) {
            $this->visitDependency($dependency, $graph, $visited, $visiting, $path);
        }

        unset($visiting[$fieldName]);
        $visited[$fieldName] = true;
    }
}
