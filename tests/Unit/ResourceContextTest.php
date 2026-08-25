<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ResourceContext;

it('defines all expected resource contexts', function (): void {
    expect(ResourceContext::cases())
        ->toHaveCount(4)
        ->and(ResourceContext::cases())->toContain(ResourceContext::Index)
        ->and(ResourceContext::cases())->toContain(ResourceContext::Show)
        ->and(ResourceContext::cases())->toContain(ResourceContext::Create)
        ->and(ResourceContext::cases())->toContain(ResourceContext::Edit);
});

it('keeps context values stable', function (): void {
    expect(ResourceContext::Index->value)->toBe('index')
        ->and(ResourceContext::Show->value)->toBe('show')
        ->and(ResourceContext::Create->value)->toBe('create')
        ->and(ResourceContext::Edit->value)->toBe('edit');
});
