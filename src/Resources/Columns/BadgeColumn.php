<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns;

class BadgeColumn extends Column
{
    /**
     * @var array<string, string>
     */
    private array $colors = [];

    /**
     * @var array<string, string>
     */
    private array $labels = [];

    /**
     * @param  array<array-key, string>  $colors
     */
    public function colors(array $colors): static
    {
        $normalized = [];

        foreach ($colors as $key => $color) {
            $normalized[(string) $key] = strtolower(trim($color));
        }

        $this->colors = $normalized;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @param  array<array-key, string>  $labels
     */
    public function labels(array $labels): static
    {
        $normalized = [];

        foreach ($labels as $key => $label) {
            $normalized[(string) $key] = $label;
        }

        $this->labels = $normalized;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }
}
