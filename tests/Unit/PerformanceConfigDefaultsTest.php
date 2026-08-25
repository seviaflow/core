<?php

declare(strict_types=1);

it('has explicit export and import row limits', function (): void {
    expect((int) config('seviaflow.export.max_rows'))->toBe(5000)
        ->and((int) config('seviaflow.import.max_rows'))->toBe(1000)
        ->and((int) config('seviaflow.import.preview_rows'))->toBe(20);
});

it('has performance pagination defaults and max guard config', function (): void {
    expect((int) config('seviaflow.performance.pagination.default_per_page'))->toBe(15)
        ->and((int) config('seviaflow.performance.pagination.max_per_page'))->toBe(100)
        ->and((string) config('seviaflow.performance.pagination.mode'))->toBe('length_aware');
});

it('has performance search default mode config', function (): void {
    expect((string) config('seviaflow.performance.search.default_mode'))->toBe('starts_with')
        ->and((string) config('seviaflow.performance.search.mode'))->toBe('starts_with');
});

it('has metadata cache placeholder config with safe defaults', function (): void {
    expect((bool) config('seviaflow.performance.cache.enabled'))->toBeFalse()
        ->and(config('seviaflow.performance.cache.store'))->toBeNull()
        ->and((int) config('seviaflow.performance.cache.ttl'))->toBe(3600)
        ->and((bool) config('seviaflow.performance.cache.resources'))->toBeTrue();
});
