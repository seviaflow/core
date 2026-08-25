<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Section;

final class FormFields
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<int, Field>
     */
    public function visible(array $fields, ResourceContext $context): array
    {
        return array_values(array_filter(
            $fields,
            static fn (Field $field): bool => $field->isVisible($context)
        ));
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<int, Field>
     */
    public function persistable(array $fields, ResourceContext $context): array
    {
        return array_values(array_filter(
            $this->visible($fields, $context),
            static fn (Field $field): bool => ! $field->isReadonly($context) && ! $field->isDisabled($context)
        ));
    }

    /**
     * @param  array<int, Section>  $sections
     * @return array<int, Section>
     */
    public function visibleSections(array $sections, ResourceContext $context): array
    {
        $visibleSections = [];

        foreach ($sections as $section) {
            $visibleFields = $this->visible($section->getFields(), $context);

            if ($visibleFields === []) {
                continue;
            }

            $visibleSections[] = Section::make($section->label())
                ->description($section->getDescription())
                ->fields($visibleFields);
        }

        return $visibleSections;
    }
}
