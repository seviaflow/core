<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Relations;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Resource;

final class MorphManyRelation
{
    private const DEFAULT_PER_PAGE = 10;

    private ?string $label = null;

    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    private int $perPage = self::DEFAULT_PER_PAGE;

    private bool $allowCreate = false;

    private bool $allowEdit = false;

    private ?string $createLabel = null;

    private ?string $editLabel = null;

    private bool $allowDelete = false;

    private bool $allowRestore = false;

    private function __construct(
        private readonly string $relationshipName
    ) {
        if (trim($relationshipName) === '') {
            throw new InvalidArgumentException('MorphManyRelation relationship name cannot be empty.');
        }
    }

    public static function make(string $relationship): self
    {
        return new self($relationship);
    }

    public function getRelationshipName(): string
    {
        return $this->relationshipName;
    }

    public function label(?string $label = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return Str::of($this->relationshipName)
            ->replace('_', ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'MorphManyRelation resource class [%s] must extend [%s].',
                $resourceClass,
                Resource::class
            ));
        }

        /** @var class-string<resource> $resourceClass */
        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @return class-string<resource>
     */
    public function getResourceClass(): string
    {
        if ($this->resourceClass === null) {
            throw new LogicException('MorphManyRelation resource class is required. Call resource() first.');
        }

        return $this->resourceClass;
    }

    public function perPage(int $perPage): static
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('MorphManyRelation per-page value must be at least 1.');
        }

        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function allowCreate(bool $allow = true): static
    {
        $this->allowCreate = $allow;

        return $this;
    }

    public function canCreate(): bool
    {
        return $this->allowCreate;
    }

    public function allowEdit(bool $allow = true): static
    {
        $this->allowEdit = $allow;

        return $this;
    }

    public function canEdit(): bool
    {
        return $this->allowEdit;
    }

    public function createLabel(string $label): static
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('MorphManyRelation create label cannot be empty.');
        }

        $this->createLabel = $label;

        return $this;
    }

    public function getCreateLabel(): string
    {
        return $this->createLabel ?? ('Add '.Str::singular($this->getLabel()));
    }

    public function editLabel(string $label): static
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('MorphManyRelation edit label cannot be empty.');
        }

        $this->editLabel = $label;

        return $this;
    }

    public function getEditLabel(): string
    {
        return $this->editLabel ?? 'Edit';
    }

    public function allowDelete(bool $allow = true): static
    {
        $this->allowDelete = $allow;

        return $this;
    }

    public function canDelete(): bool
    {
        return $this->allowDelete;
    }

    public function allowRestore(bool $allow = true): static
    {
        $this->allowRestore = $allow;

        return $this;
    }

    public function canRestore(): bool
    {
        return $this->allowRestore;
    }

    public function supportsSoftDeletes(): bool
    {
        $modelClass = $this->getResourceClass()::model();

        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }
}
