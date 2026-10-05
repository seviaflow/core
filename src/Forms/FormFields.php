<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Section;
use SeviaFlow\Core\Resources\Tab;

final class FormFields
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<int, Field>
     */
    public function visible(array $fields, ResourceContext $context, ?FormState $state = null): array
    {
        return array_values(array_filter(
            $fields,
            static fn (Field $field): bool => $field->isVisibleForState($context, $state)
        ));
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<int, Field>
     */
    public function persistable(array $fields, ResourceContext $context, ?FormState $state = null): array
    {
        return array_values(array_filter(
            $this->visible($fields, $context, $state),
            static fn (Field $field): bool => ! $field->isReadonly($context) && ! $field->isDisabledForState($context, $state)
        ));
    }

    /**
     * @param  array<int, Section>  $sections
     * @return array<int, Section>
     */
    public function visibleSections(array $sections, ResourceContext $context, ?FormState $state = null): array
    {
        $visibleSections = [];

        foreach ($sections as $section) {
            $visibleFields = $this->visible($section->getFields(), $context, $state);

            if ($visibleFields === []) {
                continue;
            }

            $visibleSection = Section::make($section->label())
                ->description($section->getDescription())
                ->fields($visibleFields);

            if ($section->getColumnSpan() !== null) {
                $visibleSection->columnSpan($section->getColumnSpan());
            }
            $visibleSection->collapsible($section->isCollapsible())->collapsed($section->isCollapsed());
            $visibleSections[] = $visibleSection;
        }

        return $visibleSections;
    }

    /**
     * @param  array<int, Tab>  $tabs
     * @return array<int, Tab>
     */
    public function visibleTabs(array $tabs, ResourceContext $context, ?FormState $state = null): array
    {
        $visibleTabs = [];

        foreach ($tabs as $tab) {
            $visibleFields = $this->visible($tab->getFields(), $context, $state);
            $visibleSections = $this->visibleSections($tab->getSections(), $context, $state);

            if ($visibleFields === [] && $visibleSections === []) {
                continue;
            }

            $visibleTab = Tab::make($tab->label())
                ->key($tab->getKey())
                ->description($tab->getDescription())
                ->fields($visibleFields)
                ->sections($visibleSections);
            $visibleTabs[] = $visibleTab;
        }

        return $visibleTabs;
    }
}
