<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns;

final class StatusColumn extends BadgeColumn
{
    /**
     * @param  array<array-key, string>  $options
     */
    public function options(array $options): static
    {
        return $this->labels($options);
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->getLabels();
    }
}
