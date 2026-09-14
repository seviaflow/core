<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use SeviaFlow\Core\Events\Resources\ResourceCreated;
use SeviaFlow\Core\Events\Resources\ResourceCreating;
use SeviaFlow\Core\Events\Resources\ResourceDeleted;
use SeviaFlow\Core\Events\Resources\ResourceDeleting;
use SeviaFlow\Core\Events\Resources\ResourceLifecycleEvent;
use SeviaFlow\Core\Events\Resources\ResourceRestored;
use SeviaFlow\Core\Events\Resources\ResourceRestoring;
use SeviaFlow\Core\Events\Resources\ResourceUpdated;
use SeviaFlow\Core\Events\Resources\ResourceUpdating;

final class ResourceEventDispatcher
{
    /**
     * @var array<string, true>
     */
    private array $dispatchedAfterCommit = [];

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    public function dispatch(
        string $resourceClass,
        string $event,
        mixed $record,
        ResourceEventContext $context
    ): void {
        $this->dispatchLaravelEvent($event, $record, $context);

        $handlers = $resourceClass::events(new ResourceEvents)->getHandlers($event);

        foreach ($handlers as $handler) {
            $this->executeHandler($handler, $record, $context, $event);
        }
    }

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    public function dispatchAfterCommit(
        string $resourceClass,
        string $event,
        mixed $record,
        ResourceEventContext $context
    ): void {
        $key = implode('|', [
            $event,
            $record instanceof Model ? $record::class : get_debug_type($record),
            $record instanceof Model ? (string) $record->getKey() : '',
            $context->correlationId(),
        ]);

        if (! $record instanceof Model || $record->getConnection()->transactionLevel() === 0) {
            $this->dispatchOnce($key, $resourceClass, $event, $record, $context);

            return;
        }

        $record->getConnection()->afterCommit(function () use ($key, $resourceClass, $event, $record, $context): void {
            $this->dispatchOnce($key, $resourceClass, $event, $record, $context);
        });
    }

    private function dispatchOnce(
        string $key,
        string $resourceClass,
        string $event,
        mixed $record,
        ResourceEventContext $context
    ): void {
        if (isset($this->dispatchedAfterCommit[$key])) {
            return;
        }

        $this->dispatch($resourceClass, $event, $record, $context);
        $this->dispatchedAfterCommit[$key] = true;
    }

    private function executeHandler(
        callable|string $handler,
        mixed $record,
        ResourceEventContext $context,
        string $event
    ): void {
        if (is_string($handler)) {
            $resolved = app($handler);

            if (! is_callable($resolved)) {
                throw new LogicException(sprintf(
                    'Resource event handler class [%s] for event [%s] must be invokable.',
                    $handler,
                    $event
                ));
            }

            $resolved($record, $context);

            return;
        }

        $handler($record, $context);
    }

    private function dispatchLaravelEvent(string $event, mixed $record, ResourceEventContext $context): void
    {
        $eventClass = $this->eventClassFor($event);

        if ($eventClass === null) {
            return;
        }

        event(new $eventClass($record, $context));
    }

    /**
     * @return class-string<ResourceLifecycleEvent>|null
     */
    private function eventClassFor(string $event): ?string
    {
        return match ($event) {
            ResourceEventContext::OPERATION_CREATING => ResourceCreating::class,
            ResourceEventContext::OPERATION_CREATED => ResourceCreated::class,
            ResourceEventContext::OPERATION_UPDATING => ResourceUpdating::class,
            ResourceEventContext::OPERATION_UPDATED => ResourceUpdated::class,
            ResourceEventContext::OPERATION_DELETING => ResourceDeleting::class,
            ResourceEventContext::OPERATION_DELETED => ResourceDeleted::class,
            ResourceEventContext::OPERATION_RESTORING => ResourceRestoring::class,
            ResourceEventContext::OPERATION_RESTORED => ResourceRestored::class,
            default => null,
        };
    }
}
