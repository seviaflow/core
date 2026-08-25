<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Metadata;

use Illuminate\Support\Str;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\ApiResource;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Filters\Filter;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;

final class ResourceMetadataResolver
{
    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resolve(string $slug, string $resourceClass): ResourceMetadataSnapshot
    {
        $table = $resourceClass::table(new Table);
        $form = $resourceClass::form(new Form);
        $showForm = $resourceClass::show(new Form);
        $workflow = $resourceClass::workflow(new Workflow);
        $api = $resourceClass::api(new ApiResource);
        $export = $resourceClass::export(new ExportResource);
        $import = $resourceClass::import(new ImportResource);

        return new ResourceMetadataSnapshot([
            'slug' => $slug,
            'resource_class' => $resourceClass,
            'model_class' => $resourceClass::model(),
            'label' => $this->resourceLabel($slug, $resourceClass),
            'navigation' => [
                'label' => $resourceClass::navigationLabel(),
                'group' => $resourceClass::navigationGroup(),
                'icon' => $resourceClass::navigationIcon(),
                'sort' => $resourceClass::navigationSort(),
                'hidden' => ! $resourceClass::shouldRegisterNavigation(),
            ],
            'table' => [
                'select_only_visible_columns' => $table->shouldSelectOnlyVisibleColumns(),
                'columns' => array_map(fn (Column $column): array => $this->columnMetadata($column), $table->getColumns()),
                'filters' => array_map(fn (Filter $filter): array => $this->filterMetadata($filter), $table->getFilters()),
            ],
            'forms' => [
                'form_fields' => $this->fieldMetadata($form),
                'show_fields' => $this->fieldMetadata($showForm),
            ],
            'actions' => array_map(fn (Action $action): array => $this->actionMetadata($action), $resourceClass::actions()),
            'bulk_actions' => array_map(fn (BulkAction $action): array => $this->bulkActionMetadata($action), $resourceClass::bulkActions()),
            'relations' => array_map(fn (HasManyRelation|BelongsToManyRelation $relation): array => $this->relationMetadata($relation), $resourceClass::relations()),
            'workflow' => $this->workflowMetadata($workflow),
            'api' => $this->apiMetadata($api),
            'export' => $this->exportMetadata($export),
            'import' => $this->importMetadata($import),
        ]);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceLabel(string $slug, string $resourceClass): string
    {
        $navigationLabel = $resourceClass::navigationLabel();

        if (is_string($navigationLabel) && trim($navigationLabel) !== '') {
            return $navigationLabel;
        }

        return (string) Str::of($slug)->replace(['_', '-'], ' ')->title();
    }

    private function columnMetadata(Column $column): array
    {
        return [
            'class' => $column::class,
            'name' => $column->name(),
            'label' => $column->getLabel(),
            'searchable' => $column->isSearchable(),
            'sortable' => $column->isSortable(),
            'search_mode' => $column->getSearchMode(),
            'has_search_callback' => $column->getSearchCallback() !== null,
        ];
    }

    private function filterMetadata(Filter $filter): array
    {
        $metadata = [
            'class' => $filter::class,
            'name' => $filter->name(),
            'label' => $filter->getLabel(),
            'placeholder' => $filter->getPlaceholder(),
            'default' => is_scalar($filter->getDefault()) || $filter->getDefault() === null ? $filter->getDefault() : null,
        ];

        if (method_exists($filter, 'getOptions')) {
            $metadata['options_count'] = is_array($filter->getOptions()) ? count($filter->getOptions()) : 0;
        }

        if (method_exists($filter, 'getMode')) {
            $metadata['mode'] = $filter->getMode();
        }

        return $metadata;
    }

    private function actionMetadata(Action $action): array
    {
        return [
            'name' => $action->name(),
            'label' => $action->getLabel(),
            'danger' => $action->isDanger(),
            'requires_confirmation' => $action->shouldRequireConfirmation(),
        ];
    }

    private function bulkActionMetadata(BulkAction $action): array
    {
        return [
            'name' => $action->name(),
            'label' => $action->getLabel(),
            'danger' => $action->isDanger(),
            'requires_confirmation' => $action->shouldRequireConfirmation(),
            'max_records' => $action->getMaxRecords(),
        ];
    }

    private function relationMetadata(HasManyRelation|BelongsToManyRelation $relation): array
    {
        try {
            $resourceClass = $relation->getResourceClass();
        } catch (\LogicException) {
            $resourceClass = null;
        }

        if ($relation instanceof HasManyRelation) {
            return [
                'type' => 'has_many',
                'relationship' => $relation->getRelationshipName(),
                'label' => $relation->getLabel(),
                'resource_class' => $resourceClass,
                'per_page' => $relation->getPerPage(),
                'allow_create' => $relation->canCreate(),
                'allow_edit' => $relation->canEdit(),
                'allow_delete' => $relation->canDelete(),
                'allow_restore' => $relation->canRestore(),
            ];
        }

        return [
            'type' => 'belongs_to_many',
            'relationship' => $relation->getRelationshipName(),
            'label' => $relation->getLabel(),
            'resource_class' => $resourceClass,
            'title_attribute' => $relation->getTitleAttribute(),
            'per_page' => $relation->getPerPage(),
            'options_limit' => $relation->getOptionsLimit(),
            'pivot_columns' => array_map(
                static fn (Column $column): array => [
                    'name' => $column->name(),
                    'label' => $column->getLabel(),
                    'class' => $column::class,
                ],
                $relation->getPivotColumns()
            ),
            'pivot_fields' => array_map(
                static fn ($field): array => [
                    'name' => $field->name(),
                    'label' => $field->getLabel(),
                    'class' => $field::class,
                ],
                $relation->getPivotFields()
            ),
        ];
    }

    private function workflowMetadata(Workflow $workflow): array
    {
        $transitions = array_map(
            fn (Transition $transition): ?array => $this->transitionMetadata($transition),
            $workflow->getTransitions()
        );

        return [
            'state_field' => $workflow->getStateField(),
            'initial_state' => $workflow->getInitialState(),
            'states' => array_map(
                static fn (State $state): array => [
                    'name' => $state->name(),
                    'label' => $state->getLabel(),
                    'color' => $state->getColor(),
                    'final' => $state->isFinal(),
                ],
                $workflow->getStates()
            ),
            'transitions' => array_values(array_filter($transitions, static fn (mixed $transition): bool => is_array($transition))),
        ];
    }

    private function transitionMetadata(Transition $transition): ?array
    {
        if ($transition->getFromStates() === []) {
            return null;
        }

        try {
            $toState = $transition->getToState();
        } catch (\LogicException) {
            return null;
        }

        return [
            'name' => $transition->name(),
            'label' => $transition->getLabel(),
            'from' => $transition->getFromStates(),
            'to' => $toState,
            'danger' => $transition->isDanger(),
            'requires_confirmation' => $transition->shouldRequireConfirmation(),
            'requires_comment' => $transition->shouldRequireComment(),
            'has_handler' => $transition->getHandler() !== null,
        ];
    }

    private function apiMetadata(ApiResource $api): array
    {
        return [
            'enabled' => $api->isEnabled(),
            'readable_fields' => $api->getReadableFields(),
            'creatable_fields' => $api->getCreatableFields(),
            'updatable_fields' => $api->getUpdatableFields(),
            'required_on_create' => $api->getRequiredOnCreateFields(),
            'required_on_update' => $api->getRequiredOnUpdateFields(),
            'deletable' => $api->isDeletable(),
            'flow_enabled' => $api->isFlowEnabled(),
            'use_form_field_rules' => $api->usesFormFieldRules(),
        ];
    }

    private function exportMetadata(ExportResource $export): array
    {
        return [
            'enabled' => $export->isEnabled(),
            'formats' => $export->getFormats(),
            'allowed_fields' => array_map(
                static fn (ExportField $field): array => [
                    'name' => $field->name(),
                    'label' => $field->getLabel(),
                ],
                $export->getAllowedFields()
            ),
        ];
    }

    private function importMetadata(ImportResource $import): array
    {
        return [
            'enabled' => $import->isEnabled(),
            'allowed_fields' => $import->getAllowedFields(),
            'required_fields' => $import->getRequiredFields(),
            'use_form_field_rules' => $import->usesFormFieldRules(),
        ];
    }

    /**
     * @return array<int, array{name:string,label:string,class:string}>
     */
    private function fieldMetadata(Form $form): array
    {
        return array_map(
            static fn ($field): array => [
                'name' => $field->name(),
                'label' => $field->getLabel(),
                'class' => $field::class,
            ],
            $form->getAllFields()
        );
    }
}
