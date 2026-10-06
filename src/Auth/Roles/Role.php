<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Roles;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $slug
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, RoleAssignment> $assignments
 */
final class Role extends Model
{
    protected $table = 'seviaflow_roles';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany<Permission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'role_id');
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'role_id');
    }
}
