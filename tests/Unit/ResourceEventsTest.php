<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\ResourceEvents;

it('can register creating handler', function (): void {
    $handler = static fn (): null => null;
    $events = (new ResourceEvents)->onCreating($handler);

    expect($events->hasHandlers('creating'))->toBeTrue()
        ->and($events->getHandlers('creating'))->toHaveCount(1)
        ->and($events->getHandlers('creating')[0])->toBe($handler);
});

it('can register created handler', function (): void {
    $events = (new ResourceEvents)->onCreated(ResourceEventsInvokableHandler::class);

    expect($events->hasHandlers('created'))->toBeTrue()
        ->and($events->getHandlers('created'))->toBe([ResourceEventsInvokableHandler::class]);
});

it('can register multiple handlers', function (): void {
    $first = static fn (): null => null;
    $second = static fn (): null => null;

    $events = (new ResourceEvents)
        ->onUpdating([$first, ResourceEventsInvokableHandler::class])
        ->onUpdating($second);

    expect($events->getHandlers('updating'))->toHaveCount(3)
        ->and($events->getHandlers('updating')[0])->toBe($first)
        ->and($events->getHandlers('updating')[1])->toBe(ResourceEventsInvokableHandler::class)
        ->and($events->getHandlers('updating')[2])->toBe($second);
});

it('stores closure handlers as callables', function (): void {
    $handler = static fn (): null => null;
    $events = (new ResourceEvents)->onDeleted($handler);

    expect(is_callable($events->getHandlers('deleted')[0]))->toBeTrue();
});

it('stores invokable class handler strings', function (): void {
    $events = (new ResourceEvents)->onRestored(ResourceEventsInvokableHandler::class);

    expect($events->getHandlers('restored'))->toBe([ResourceEventsInvokableHandler::class]);
});

it('fails clearly when handler type is invalid', function (): void {
    expect(fn () => (new ResourceEvents)->onCreating([new stdClass]))
        ->toThrow(InvalidArgumentException::class, 'ResourceEvents::creating handler at index 0 must be callable|string');
});

it('fails clearly when string handler is empty', function (): void {
    expect(fn () => (new ResourceEvents)->onCreated('   '))
        ->toThrow(InvalidArgumentException::class, 'ResourceEvents::created handler at index 0 must be a non-empty string.');
});

it('fails clearly when event name is invalid in getters', function (): void {
    expect(fn () => (new ResourceEvents)->getHandlers('invalid'))
        ->toThrow(InvalidArgumentException::class, 'ResourceEvents::event [invalid] is not supported');
});

it('all v1 event methods exist and are callable', function (): void {
    $events = new ResourceEvents;

    $reflection = new ReflectionClass($events);

    expect($reflection->hasMethod('onCreating'))->toBeTrue()
        ->and($reflection->hasMethod('onCreated'))->toBeTrue()
        ->and($reflection->hasMethod('onUpdating'))->toBeTrue()
        ->and($reflection->hasMethod('onUpdated'))->toBeTrue()
        ->and($reflection->hasMethod('onDeleting'))->toBeTrue()
        ->and($reflection->hasMethod('onDeleted'))->toBeTrue()
        ->and($reflection->hasMethod('onRestoring'))->toBeTrue()
        ->and($reflection->hasMethod('onRestored'))->toBeTrue();
});

final class ResourceEventsInvokableHandler
{
    public function __invoke(): void {}
}
