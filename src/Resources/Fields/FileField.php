<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionFunction;
use SeviaFlow\Core\Resources\ResourceContext;
use Throwable;

class FileField extends Field
{
    protected static string $type = 'file';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    private string $disk = 'public';

    private string $directory = '';

    private string $visibility = self::VISIBILITY_PUBLIC;

    private ?int $maxSize = null;

    /**
     * @var array<int, string>
     */
    private array $acceptedFileTypes = [];

    private ?Closure $authorizeRead = null;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function directory(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function visibility(string $visibility): static
    {
        $visibility = strtolower(trim($visibility));

        if (! in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE], true)) {
            throw new InvalidArgumentException(sprintf(
                'File field visibility must be [%s] or [%s]. [%s] given.',
                self::VISIBILITY_PUBLIC,
                self::VISIBILITY_PRIVATE,
                $visibility
            ));
        }

        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function authorizeRead(callable $callback): static
    {
        $this->authorizeRead = Closure::fromCallable($callback);

        return $this;
    }

    public function canRead(mixed $user, ?Model $record = null): bool
    {
        if ($this->authorizeRead === null) {
            return $this->visibility === self::VISIBILITY_PUBLIC;
        }

        try {
            $parameterCount = (new ReflectionFunction($this->authorizeRead))->getNumberOfParameters();

            return match ($parameterCount) {
                0 => (bool) ($this->authorizeRead)(),
                1 => (bool) ($this->authorizeRead)($record),
                default => (bool) ($this->authorizeRead)($record, $user),
            };
        } catch (Throwable) {
            return false;
        }
    }

    public function maxSize(int $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    /**
     * @param  array<int, string>  $types
     */
    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedFileTypes(): array
    {
        return $this->acceptedFileTypes;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $rules = ['file'];

        if ($this->maxSize !== null) {
            $rules[] = 'max:'.$this->maxSize;
        }

        if ($this->acceptedFileTypes !== []) {
            $rules[] = 'mimetypes:'.implode(',', $this->acceptedFileTypes);
        }

        return $rules;
    }

    protected function defaultApiSchema(): array
    {
        return ['type' => 'string', 'format' => 'uri', 'nullable' => true];
    }
}
