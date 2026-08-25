<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use SeviaFlow\Core\Events\Resources\ResourceCreated;
use SeviaFlow\Core\Events\Resources\ResourceDeleted;
use SeviaFlow\Core\Events\Resources\ResourceRestored;
use SeviaFlow\Core\Events\Resources\ResourceUpdated;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;
use SeviaFlow\Core\Resources\ResourceEvents;

it('dispatcher executes closure handler', function (): void {
    ResourceEventDispatcherCapture::$calls = resourceEventDispatcherCalls();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherClosureResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherClosureResource::class, 'created', $record, $context);

    expect(ResourceEventDispatcherCapture::$calls)->toHaveCount(1)
        ->and(ResourceEventDispatcherCapture::$calls[0]['type'])->toBe('closure');
});

it('dispatcher executes invokable class handler', function (): void {
    ResourceEventDispatcherCapture::$calls = resourceEventDispatcherCalls();

    app()->instance(ResourceEventDispatcherInvokableHandler::class, new ResourceEventDispatcherInvokableHandler);

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherClassResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherClassResource::class, 'created', $record, $context);

    expect(ResourceEventDispatcherCapture::$calls)->toHaveCount(1)
        ->and(ResourceEventDispatcherCapture::$calls[0]['type'])->toBe('class');
});

it('dispatcher executes multiple handlers in order', function (): void {
    ResourceEventDispatcherCapture::$calls = resourceEventDispatcherCalls();

    app()->instance(ResourceEventDispatcherInvokableHandler::class, new ResourceEventDispatcherInvokableHandler);

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherMultipleHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherMultipleHandlersResource::class, 'updated', $record, $context);

    expect(ResourceEventDispatcherCapture::$calls)->toHaveCount(3)
        ->and(ResourceEventDispatcherCapture::$calls[0]['type'])->toBe('first')
        ->and(ResourceEventDispatcherCapture::$calls[1]['type'])->toBe('class')
        ->and(ResourceEventDispatcherCapture::$calls[2]['type'])->toBe('last');
});

it('dispatcher passes record and context', function (): void {
    ResourceEventDispatcherCapture::$calls = resourceEventDispatcherCalls();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherClosureResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherClosureResource::class, 'created', $record, $context);

    expect(ResourceEventDispatcherCapture::$calls)->toHaveCount(1)
        ->and(ResourceEventDispatcherCapture::$calls[0]['record'])->toBe($record)
        ->and(ResourceEventDispatcherCapture::$calls[0]['context'])->toBe($context);
});

it('no handlers is safe no-op', function (): void {
    ResourceEventDispatcherCapture::$calls = resourceEventDispatcherCalls();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherNoHandlersResource::class, 'created', $record, $context);

    expect(ResourceEventDispatcherCapture::$calls)->toBe([]);
});

it('handler exception bubbles', function (): void {
    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherThrowingResource::class);

    expect(fn () => $dispatcher->dispatch(ResourceEventDispatcherThrowingResource::class, 'deleted', $record, $context))
        ->toThrow(RuntimeException::class, 'Handler failed');
});

it('invalid handler class fails clearly', function (): void {
    app()->instance(ResourceEventDispatcherNonInvokableHandler::class, new ResourceEventDispatcherNonInvokableHandler);

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherInvalidClassResource::class);

    expect(fn () => $dispatcher->dispatch(ResourceEventDispatcherInvalidClassResource::class, 'created', $record, $context))
        ->toThrow(LogicException::class, 'Resource event handler class ['.ResourceEventDispatcherNonInvokableHandler::class.'] for event [created] must be invokable.');
});

it('invalid handler type fails clearly', function (): void {
    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherInvalidTypeResource::class);

    expect(fn () => $dispatcher->dispatch(ResourceEventDispatcherInvalidTypeResource::class, 'created', $record, $context))
        ->toThrow(InvalidArgumentException::class, 'ResourceEvents::created handler at index 0 must be callable|string');
});

it('resource created laravel event is dispatched', function (): void {
    Event::fake();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherNoHandlersResource::class, ResourceEventContext::OPERATION_CREATED, $record, $context);

    Event::assertDispatched(ResourceCreated::class, function (ResourceCreated $event) use ($record, $context): bool {
        return $event->record === $record && $event->context === $context;
    });
});

it('resource updated laravel event is dispatched', function (): void {
    Event::fake();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherNoHandlersResource::class, ResourceEventContext::OPERATION_UPDATED, $record, $context);

    Event::assertDispatched(ResourceUpdated::class, 1);
});

it('resource deleted laravel event is dispatched', function (): void {
    Event::fake();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherNoHandlersResource::class, ResourceEventContext::OPERATION_DELETED, $record, $context);

    Event::assertDispatched(ResourceDeleted::class, 1);
});

it('resource restored laravel event is dispatched', function (): void {
    Event::fake();

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    $dispatcher->dispatch(ResourceEventDispatcherNoHandlersResource::class, ResourceEventContext::OPERATION_RESTORED, $record, $context);

    Event::assertDispatched(ResourceRestored::class, 1);
});

it('laravel listener exception bubbles predictably', function (): void {
    Event::listen(ResourceCreated::class, static function (): void {
        throw new RuntimeException('Listener failed');
    });

    $dispatcher = new ResourceEventDispatcher;
    $record = new ResourceEventDispatcherRecord;
    $context = resourceEventContextFor(ResourceEventDispatcherNoHandlersResource::class);

    expect(fn () => $dispatcher->dispatch(
        ResourceEventDispatcherNoHandlersResource::class,
        ResourceEventContext::OPERATION_CREATED,
        $record,
        $context
    ))->toThrow(RuntimeException::class, 'Listener failed');
});

/**
 * @param  class-string<resource>  $resourceClass
 */
function resourceEventContextFor(string $resourceClass): ResourceEventContext
{
    return new ResourceEventContext(
        user: null,
        resourceClass: $resourceClass,
        resourceSlug: 'demo',
        operation: ResourceEventContext::OPERATION_CREATED,
        source: ResourceEventContext::SOURCE_SYSTEM,
    );
}

/**
 * @return array<int, array{type:string, record:mixed, context:ResourceEventContext}>
 */
function resourceEventDispatcherCalls(): array
{
    return [];
}

final class ResourceEventDispatcherCapture
{
    /**
     * @var array<int, array{type:string, record:mixed, context:ResourceEventContext}>
     */
    public static array $calls = [];
}

final class ResourceEventDispatcherRecord extends Model
{
    protected $guarded = [];
}

final class ResourceEventDispatcherClosureResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onCreated(function (mixed $record, ResourceEventContext $context): void {
            ResourceEventDispatcherCapture::$calls[] = [
                'type' => 'closure',
                'record' => $record,
                'context' => $context,
            ];
        });
    }
}

final class ResourceEventDispatcherClassResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onCreated(ResourceEventDispatcherInvokableHandler::class);
    }
}

final class ResourceEventDispatcherMultipleHandlersResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onUpdated([
            static function (mixed $record, ResourceEventContext $context): void {
                ResourceEventDispatcherCapture::$calls[] = [
                    'type' => 'first',
                    'record' => $record,
                    'context' => $context,
                ];
            },
            ResourceEventDispatcherInvokableHandler::class,
            static function (mixed $record, ResourceEventContext $context): void {
                ResourceEventDispatcherCapture::$calls[] = [
                    'type' => 'last',
                    'record' => $record,
                    'context' => $context,
                ];
            },
        ]);
    }
}

final class ResourceEventDispatcherNoHandlersResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;
}

final class ResourceEventDispatcherThrowingResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onDeleted(function (): void {
            throw new RuntimeException('Handler failed');
        });
    }
}

final class ResourceEventDispatcherInvalidClassResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onCreated(ResourceEventDispatcherNonInvokableHandler::class);
    }
}

final class ResourceEventDispatcherInvalidTypeResource extends Resource
{
    protected static string $model = ResourceEventDispatcherRecord::class;

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events->onCreated([new stdClass]);
    }
}

final class ResourceEventDispatcherInvokableHandler
{
    public function __invoke(mixed $record, ResourceEventContext $context): void
    {
        ResourceEventDispatcherCapture::$calls[] = [
            'type' => 'class',
            'record' => $record,
            'context' => $context,
        ];
    }
}

final class ResourceEventDispatcherNonInvokableHandler
{
    public function run(): void {}
}
