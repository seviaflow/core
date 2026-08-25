<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support\Capabilities;

final class CapabilityRegistry
{
    /** @var array<string, Capability> */
    private array $capabilities = [];

    public function register(string $name, bool $enabled = true, ?string $description = null): void
    {
        $this->capabilities[$name] = new Capability($name, $enabled, $description);
    }

    /**
     * @return array<string, Capability>
     */
    public function all(): array
    {
        ksort($this->capabilities);

        return $this->capabilities;
    }

    public function enabled(string $name): bool
    {
        return ($this->capabilities[$name] ?? null)?->enabled ?? false;
    }
}
