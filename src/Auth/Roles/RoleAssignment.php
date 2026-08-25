<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class RoleAssignment extends Model
{
    protected $table = 'seviaflow_roleables';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'model_type',
        'model_id',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
