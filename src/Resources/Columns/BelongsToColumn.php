<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Columns;

use InvalidArgumentException;
use LogicException;

final class BelongsToColumn extends Column
{
    private ?string $relationshipName = null;

    private ?string $titleAttribute = null;

    public function relationship(string $relationship, string $titleAttribute = 'id'): static
    {
        $relationship = trim($relationship);
        $titleAttribute = trim($titleAttribute);

        if ($relationship === '') {
            throw new InvalidArgumentException('BelongsToColumn relationship name cannot be empty.');
        }

        if ($titleAttribute === '') {
            throw new InvalidArgumentException('BelongsToColumn title attribute cannot be empty.');
        }

        $this->relationshipName = $relationship;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function getRelationshipName(): string
    {
        if ($this->relationshipName === null) {
            throw new LogicException('BelongsToColumn relationship metadata is required. Call relationship() first.');
        }

        return $this->relationshipName;
    }

    public function getTitleAttribute(): string
    {
        if ($this->titleAttribute === null) {
            throw new LogicException('BelongsToColumn relationship metadata is required. Call relationship() first.');
        }

        return $this->titleAttribute;
    }
}
