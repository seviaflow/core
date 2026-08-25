<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;

final class ResourceEvents
{
    private const EVENT_CREATING = 'creating';

    private const EVENT_CREATED = 'created';

    private const EVENT_UPDATING = 'updating';

    private const EVENT_UPDATED = 'updated';

    private const EVENT_DELETING = 'deleting';

    private const EVENT_DELETED = 'deleted';

    private const EVENT_RESTORING = 'restoring';

    private const EVENT_RESTORED = 'restored';

    /**
     * @var array<string, array<int, callable|string>>
     */
    private array $handlers = [
        self::EVENT_CREATING => [],
        self::EVENT_CREATED => [],
        self::EVENT_UPDATING => [],
        self::EVENT_UPDATED => [],
        self::EVENT_DELETING => [],
        self::EVENT_DELETED => [],
        self::EVENT_RESTORING => [],
        self::EVENT_RESTORED => [],
    ];

    public function onCreating(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_CREATING, $handlers);
    }

    public function onCreated(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_CREATED, $handlers);
    }

    public function onUpdating(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_UPDATING, $handlers);
    }

    public function onUpdated(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_UPDATED, $handlers);
    }

    public function onDeleting(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_DELETING, $handlers);
    }

    public function onDeleted(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_DELETED, $handlers);
    }

    public function onRestoring(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_RESTORING, $handlers);
    }

    public function onRestored(callable|string|array $handlers): static
    {
        return $this->registerHandlers(self::EVENT_RESTORED, $handlers);
    }

    /**
     * @return array<int, callable|string>
     */
    public function getHandlers(string $event): array
    {
        $this->assertKnownEvent($event);

        return $this->handlers[$event];
    }

    public function hasHandlers(string $event): bool
    {
        $this->assertKnownEvent($event);

        return $this->handlers[$event] !== [];
    }

    private function registerHandlers(string $event, callable|string|array $handlers): static
    {
        $this->assertKnownEvent($event);

        $normalized = $this->normalizeHandlers($handlers, $event);
        $this->handlers[$event] = array_values(array_merge($this->handlers[$event], $normalized));

        return $this;
    }

    /**
     * @return array<int, callable|string>
     */
    private function normalizeHandlers(callable|string|array $handlers, string $event): array
    {
        $values = is_array($handlers) ? $handlers : [$handlers];
        $normalized = [];

        foreach ($values as $index => $handler) {
            if (is_string($handler)) {
                $value = trim($handler);

                if ($value === '') {
                    throw new InvalidArgumentException(sprintf(
                        'ResourceEvents::%s handler at index %d must be a non-empty string.',
                        $event,
                        $index
                    ));
                }

                $normalized[] = $value;

                continue;
            }

            if (is_callable($handler)) {
                $normalized[] = $handler;

                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'ResourceEvents::%s handler at index %d must be callable|string, [%s] given.',
                $event,
                $index,
                get_debug_type($handler)
            ));
        }

        return $normalized;
    }

    private function assertKnownEvent(string $event): void
    {
        if (! array_key_exists($event, $this->handlers)) {
            throw new InvalidArgumentException(sprintf(
                'ResourceEvents::event [%s] is not supported. Supported events: %s.',
                $event,
                implode(', ', array_keys($this->handlers))
            ));
        }
    }
}
