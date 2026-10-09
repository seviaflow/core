<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Events\Auth;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

final class RoleAssignmentsChanged implements ShouldDispatchAfterCommit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Model $user,
        public readonly array $context = [],
    ) {}
}
