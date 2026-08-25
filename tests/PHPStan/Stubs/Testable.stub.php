<?php

declare(strict_types=1);

namespace Livewire\Features\SupportTesting;

class Testable
{
    public function assertForbidden(): static {}

    public function assertSet(string $name, mixed $value = null): static {}

    /**
     * @param  string|array<int, string>  $value
     */
    public function assertSee(string|array $value, bool $escape = true): static {}

    /**
     * @param  string|array<int, string>  $value
     */
    public function assertDontSee(string|array $value, bool $escape = true): static {}

    /**
     * @param  array<int, string>  $values
     */
    public function assertSeeInOrder(array $values, bool $escape = true): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertHasErrors(array|string $keys = [], ?string $format = null): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertHasNoErrors(array|string $keys = []): static {}

    public function set(string $name, mixed $value = null): static {}

    public function call(string $method, mixed ...$parameters): static {}

    public function assertFileDownloaded(?string $filename = null): static {}
}
