<?php

declare(strict_types=1);

namespace Illuminate\Testing;

class TestResponse
{
    /**
     * @param  class-string  $component
     */
    public function assertSeeLivewire(string $component): static {}

    public function assertOk(): static {}

    public function assertCreated(): static {}

    public function assertForbidden(): static {}

    public function assertNotFound(): static {}

    public function assertSuccessful(): static {}

    public function assertFailed(): static {}

    public function assertStatus(int $status): static {}

    public function assertRedirect(?string $uri = null): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertSessionHasErrors(array|string $keys = [], ?string $format = null): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertHasErrors(array|string $keys = [], ?string $format = null): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertHasNoErrors(array|string $keys = []): static {}

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

    public function assertJsonPath(string $path, mixed $expect): static {}

    public function assertJsonCount(int $count, ?string $key = null): static {}

    public function assertJsonMissingPath(string $path): static {}

    /**
     * @param  array<int, mixed>|null  $structure
     */
    public function assertJsonStructure(?array $structure = null, mixed $responseData = null): static {}

    /**
     * @param  array<int|string, string>|string  $keys
     */
    public function assertJsonValidationErrors(array|string $keys, string $responseKey = 'errors'): static {}

    public function assertExists(string $path): static {}

    public function assertFileDownloaded(?string $filename = null): static {}

    public function json(?string $key = null, mixed $default = null): mixed {}

    public function getContent(): string {}
}
