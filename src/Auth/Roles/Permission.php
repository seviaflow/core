<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|string $role_id
 * @property string $resource
 * @property string $ability
 * @property bool $allowed
 * @property-read BelongsTo<Role, $this> $role
 */
final class Permission extends Model
{
    protected $table = 'seviaflow_permissions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'resource',
        'ability',
        'allowed',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'allowed' => 'boolean',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
