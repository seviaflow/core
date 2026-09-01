<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;

final class AnalyticsDataset
{
    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    private ?string $label = null;

    /**
     * @var array<string, Measure>
     */
    private array $measures = [];

    /**
     * @var array<string, Dimension>
     */
    private array $dimensions = [];

    /**
     * @var array<string, TimeDimension>
     */
    private array $timeDimensions = [];

    /**
     * @var callable|null
     */
    private $authorizationCallback = null;

    private function __construct(
        private readonly string $key,
    ) {
        self::assertKey($key, 'dataset key');
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(?string $label): self
    {
        $this->label = $label !== null ? trim($label) : null;

        return $this;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resource(string $resourceClass): self
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf('Analytics dataset resource [%s] must extend %s.', $resourceClass, Resource::class));
        }

        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @param  array<int, Measure>  $measures
     */
    public function measures(array $measures): self
    {
        $this->measures = [];

        foreach ($measures as $measure) {
            if (! $measure instanceof Measure) {
                throw new InvalidArgumentException('AnalyticsDataset::measures() accepts only Measure instances.');
            }

            $this->measures[$measure->key()] = $measure;
        }

        $this->assertUniqueAliases($this->measures, 'measure');

        return $this;
    }

    /**
     * @param  array<int, Dimension>  $dimensions
     */
    public function dimensions(array $dimensions): self
    {
        $this->dimensions = [];

        foreach ($dimensions as $dimension) {
            if (! $dimension instanceof Dimension) {
                throw new InvalidArgumentException('AnalyticsDataset::dimensions() accepts only Dimension instances.');
            }

            $this->dimensions[$dimension->key()] = $dimension;
        }

        $this->assertUniqueAliases($this->dimensions, 'dimension');

        return $this;
    }

    /**
     * @param  array<int, TimeDimension>  $timeDimensions
     */
    public function timeDimensions(array $timeDimensions): self
    {
        $this->timeDimensions = [];

        foreach ($timeDimensions as $dimension) {
            if (! $dimension instanceof TimeDimension) {
                throw new InvalidArgumentException('AnalyticsDataset::timeDimensions() accepts only TimeDimension instances.');
            }

            $this->timeDimensions[$dimension->key()] = $dimension;
        }

        $this->assertUniqueAliases($this->timeDimensions, 'time dimension');

        return $this;
    }

    public function authorize(callable $callback): self
    {
        $this->authorizationCallback = $callback;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return is_string($this->label) && $this->label !== ''
            ? $this->label
            : str((string) str_replace(['_', '-'], ' ', $this->key))->headline()->toString();
    }

    /**
     * @return class-string<resource>
     */
    public function resourceClass(): string
    {
        if ($this->resourceClass === null) {
            throw new InvalidArgumentException(sprintf('Analytics dataset [%s] must define a Resource.', $this->key));
        }

        return $this->resourceClass;
    }

    /**
     * @return array<string, Measure>
     */
    public function measuresDefinition(): array
    {
        return $this->measures;
    }

    /**
     * @return array<string, Dimension>
     */
    public function dimensionsDefinition(): array
    {
        return $this->dimensions;
    }

    /**
     * @return array<string, TimeDimension>
     */
    public function timeDimensionsDefinition(): array
    {
        return $this->timeDimensions;
    }

    public function measure(string $key): Measure
    {
        if (! isset($this->measures[$key])) {
            throw new InvalidArgumentException(sprintf('Analytics measure [%s] is not registered on dataset [%s].', $key, $this->key));
        }

        return $this->measures[$key];
    }

    public function dimension(string $key): Dimension
    {
        if (! isset($this->dimensions[$key])) {
            throw new InvalidArgumentException(sprintf('Analytics dimension [%s] is not registered on dataset [%s].', $key, $this->key));
        }

        return $this->dimensions[$key];
    }

    public function timeDimension(string $key): TimeDimension
    {
        if (! isset($this->timeDimensions[$key])) {
            throw new InvalidArgumentException(sprintf('Analytics time dimension [%s] is not registered on dataset [%s].', $key, $this->key));
        }

        return $this->timeDimensions[$key];
    }

    public function hasDimension(string $key): bool
    {
        return isset($this->dimensions[$key]);
    }

    public function hasTimeDimension(string $key): bool
    {
        return isset($this->timeDimensions[$key]);
    }

    public function canAccess(mixed $user = null): bool
    {
        if (! ResourceAuthorization::for($this->resourceClass())->canViewAny($user instanceof Authenticatable ? $user : null)) {
            return false;
        }

        if (! is_callable($this->authorizationCallback)) {
            return true;
        }

        return (bool) call_user_func($this->authorizationCallback, $user, $this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        return [
            'key' => $this->key,
            'label' => $this->getLabel(),
            'resource' => $this->resourceClass(),
            'measures' => array_map(
                static fn (Measure $measure): array => $measure->toArray(),
                array_filter($this->measures, static fn (Measure $measure): bool => $measure->canAccess($user))
            ),
            'dimensions' => array_map(static fn (Dimension $dimension): array => $dimension->toArray(), $this->dimensions),
            'time_dimensions' => array_map(static fn (TimeDimension $dimension): array => $dimension->toArray(), $this->timeDimensions),
        ];
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }

    /**
     * @param  array<string, Measure|Dimension|TimeDimension>  $items
     */
    private function assertUniqueAliases(array $items, string $type): void
    {
        $seen = [];

        foreach ($items as $key => $item) {
            foreach ($item->aliasesDefinition() as $alias) {
                $fingerprint = mb_strtolower($alias);

                if (isset($seen[$fingerprint])) {
                    throw new InvalidArgumentException(sprintf(
                        'Analytics %s alias [%s] is duplicated by [%s] and [%s].',
                        $type,
                        $alias,
                        $seen[$fingerprint],
                        $key
                    ));
                }

                $seen[$fingerprint] = $key;
            }
        }
    }
}
