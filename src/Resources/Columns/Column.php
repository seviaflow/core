<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\ResourceContext;

abstract class Column
{
    private ?string $label = null;

    private bool $searchable = false;

    private bool $sortable = false;

    private ?string $searchMode = null;

    private ?Closure $searchCallback = null;

    /**
     * @var array<string, bool>
     */
    private array $visibility = [
        ResourceContext::Index->value => true,
        ResourceContext::Show->value => true,
        ResourceContext::Create->value => false,
        ResourceContext::Edit->value => false,
    ];

    final protected function __construct(
        private readonly string $name
    ) {}

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

    public function searchable(bool $condition = true, ?string $mode = null): static
    {
        $this->searchable = $condition;

        if ($mode !== null) {
            $this->searchMode = $this->normalizeSearchMode($mode);
        }

        return $this;
    }

    public function searchableUsing(Closure $callback): static
    {
        $this->searchable = true;
        $this->searchCallback = $callback;

        return $this;
    }

    public function sortable(bool $condition = true): static
    {
        $this->sortable = $condition;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function getSearchMode(): ?string
    {
        return $this->searchMode;
    }

    public function getSearchCallback(): ?Closure
    {
        return $this->searchCallback;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function visibleOn(ResourceContext|array|string $contexts): static
    {
        foreach (ResourceContext::cases() as $context) {
            $this->visibility[$context->value] = false;
        }

        foreach ($this->normalizeContexts($contexts) as $context) {
            $this->visibility[$context->value] = true;
        }

        return $this;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function hiddenOn(ResourceContext|array|string $contexts): static
    {
        foreach ($this->normalizeContexts($contexts) as $context) {
            $this->visibility[$context->value] = false;
        }

        return $this;
    }

    public function visibleOnIndex(): static
    {
        $this->visibility[ResourceContext::Index->value] = true;

        return $this;
    }

    public function hiddenOnIndex(): static
    {
        $this->visibility[ResourceContext::Index->value] = false;

        return $this;
    }

    public function isVisible(ResourceContext|string $context): bool
    {
        $normalized = $this->normalizeContext($context);

        return $this->visibility[$normalized->value] ?? false;
    }

    private function normalizeSearchMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        $allowedModes = ['contains', 'starts_with', 'exact'];

        if (! in_array($normalized, $allowedModes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported search mode [%s]. Allowed modes: %s.',
                $mode,
                implode(', ', $allowedModes)
            ));
        }

        return $normalized;
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     * @return array<int, ResourceContext>
     */
    private function normalizeContexts(ResourceContext|array|string $contexts): array
    {
        if (! is_array($contexts)) {
            return [$this->normalizeContext($contexts)];
        }

        if ($contexts === []) {
            throw new InvalidArgumentException('At least one resource context must be provided.');
        }

        return array_map(
            fn (ResourceContext|string $context): ResourceContext => $this->normalizeContext($context),
            $contexts
        );
    }

    private function normalizeContext(ResourceContext|string $context): ResourceContext
    {
        if ($context instanceof ResourceContext) {
            return $context;
        }

        $normalized = ResourceContext::tryFrom(strtolower(trim($context)));

        if ($normalized === null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid resource context [%s]. Allowed values: %s.',
                $context,
                implode(', ', array_map(
                    static fn (ResourceContext $allowed): string => $allowed->value,
                    ResourceContext::cases()
                ))
            ));
        }

        return $normalized;
    }
}
