<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SeviaFlow\Core\Support\SensitiveDataPolicy;

final class ResourceEventContext
{
    public const SOURCE_FORM = 'form';

    public const SOURCE_INDEX = 'index';

    public const SOURCE_SHOW = 'show';

    public const SOURCE_API = 'api';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_INLINE_CREATE = 'inline_create';

    public const SOURCE_RELATION = 'relation';

    public const SOURCE_ACTION = 'action';

    public const SOURCE_BULK_ACTION = 'bulk_action';

    public const SOURCE_WORKFLOW = 'workflow';

    public const SOURCE_SYSTEM = 'system';

    public const OPERATION_CREATING = 'creating';

    public const OPERATION_CREATED = 'created';

    public const OPERATION_UPDATING = 'updating';

    public const OPERATION_UPDATED = 'updated';

    public const OPERATION_DELETING = 'deleting';

    public const OPERATION_DELETED = 'deleted';

    public const OPERATION_RESTORING = 'restoring';

    public const OPERATION_RESTORED = 'restored';

    private readonly array $original;

    private readonly array $changes;

    private readonly array $metadata;

    private readonly string $correlationId;

    public function __construct(
        private readonly mixed $user,
        private readonly string $resourceClass,
        private readonly string $resourceSlug,
        private readonly string $operation,
        private readonly string $source,
        private readonly ?Request $request = null,
        array $original = [],
        array $changes = [],
        array $metadata = [],
        private readonly ?string $connection = null,
        ?string $correlationId = null,
    ) {
        $this->assertSupportedOperation($this->operation);
        $this->assertSupportedSource($this->source);
        $this->original = SensitiveDataPolicy::redact($original);
        $this->changes = SensitiveDataPolicy::redact($changes);
        $this->metadata = SensitiveDataPolicy::redact($metadata);
        $this->correlationId = trim((string) $correlationId) ?: (string) Str::uuid();
    }

    public function user(): mixed
    {
        return $this->user;
    }

    public function actor(): mixed
    {
        return $this->user;
    }

    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    public function resourceSlug(): string
    {
        return $this->resourceSlug;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function original(): array
    {
        return $this->original;
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<int, string>
     */
    public static function supportedSources(): array
    {
        return [
            self::SOURCE_FORM,
            self::SOURCE_INDEX,
            self::SOURCE_SHOW,
            self::SOURCE_API,
            self::SOURCE_IMPORT,
            self::SOURCE_INLINE_CREATE,
            self::SOURCE_RELATION,
            self::SOURCE_ACTION,
            self::SOURCE_BULK_ACTION,
            self::SOURCE_WORKFLOW,
            self::SOURCE_SYSTEM,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedOperations(): array
    {
        return [
            self::OPERATION_CREATING,
            self::OPERATION_CREATED,
            self::OPERATION_UPDATING,
            self::OPERATION_UPDATED,
            self::OPERATION_DELETING,
            self::OPERATION_DELETED,
            self::OPERATION_RESTORING,
            self::OPERATION_RESTORED,
        ];
    }

    private function assertSupportedOperation(string $operation): void
    {
        if (in_array($operation, self::supportedOperations(), true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'ResourceEventContext operation [%s] is not supported. Supported operations: %s.',
            $operation,
            implode(', ', self::supportedOperations())
        ));
    }

    private function assertSupportedSource(string $source): void
    {
        if (in_array($source, self::supportedSources(), true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'ResourceEventContext source [%s] is not supported. Supported sources: %s.',
            $source,
            implode(', ', self::supportedSources())
        ));
    }
}
