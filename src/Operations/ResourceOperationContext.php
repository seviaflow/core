<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Operations;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\ResourceEventContext;

final class ResourceOperationContext
{
    private readonly string $correlationId;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly mixed $actor = null,
        private readonly string $source = ResourceEventContext::SOURCE_SYSTEM,
        private readonly ?Request $request = null,
        private readonly ?string $connection = null,
        private readonly array $metadata = [],
        ?string $correlationId = null,
    ) {
        if (! in_array($this->source, ResourceEventContext::supportedSources(), true)) {
            throw new InvalidArgumentException(sprintf(
                'ResourceOperationContext source [%s] is not supported.',
                $this->source
            ));
        }

        $this->correlationId = trim((string) $correlationId) ?: (string) Str::uuid();
    }

    public function actor(): mixed
    {
        return $this->actor;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    public function connection(): ?string
    {
        $connection = $this->connection === null ? null : trim($this->connection);

        return $connection === '' ? null : $connection;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }
}
