<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

class FileField extends Field
{
    private string $disk = 'public';

    private string $directory = '';

    private ?int $maxSize = null;

    /**
     * @var array<int, string>
     */
    private array $acceptedFileTypes = [];

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
}
