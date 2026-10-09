<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Events\Auth\RoleAssignmentsChanged;

final class RoleAssignmentService
{
    /**
     * @return array<int, int>
     */
    public function roleIdsFor(Model $user): array
    {
        /** @var array<int, int|string> $ids */
        $ids = RoleAssignment::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->pluck('role_id')
            ->all();

        return array_values(array_map(static fn (int|string $id): int => (int) $id, $ids));
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRoles(Model $user, array $roleIds, array $context = []): void
    {
        $normalized = array_values(array_unique(array_map(static fn (int|string $id): int => (int) $id, $roleIds)));
        $existingRoleIds = Role::query()
            ->whereIn('id', $normalized)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        RoleAssignment::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->whereNotIn('role_id', $existingRoleIds)
            ->delete();

        if ($existingRoleIds === []) {
            $this->dispatchChanged($user, $context);

            return;
        }

        /** @var Collection<int, RoleAssignment> $current */
        $current = RoleAssignment::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->get();

        $currentRoleIds = $current->pluck('role_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        foreach ($existingRoleIds as $roleId) {
            if (in_array($roleId, $currentRoleIds, true)) {
                continue;
            }

            RoleAssignment::query()->create([
                'role_id' => $roleId,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->getKey(),
            ]);
        }

        $this->dispatchChanged($user, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchChanged(Model $user, array $context): void
    {
        event(new RoleAssignmentsChanged($user, array_merge([
            'connection' => $user->getConnectionName(),
        ], $context)));
    }
}
