<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Metadata;

final class ResourceMetadataSnapshot
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
