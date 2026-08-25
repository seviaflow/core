<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $record_type
 * @property string|int $record_id
 * @property string $resource
 * @property string $transition
 * @property string|null $from_state
 * @property string $to_state
 * @property string|null $comment
 * @property string $user_type
 * @property string|int $user_id
 * @property-read Carbon|null $created_at
 */
final class WorkflowActivity extends Model
{
    protected $table = 'seviaflow_workflow_activities';

    protected $guarded = [];

    protected $casts = [
        'record_id' => 'string',
        'user_id' => 'string',
    ];

    public function scopeForRecord(Builder $query, Model $record): Builder
    {
        return $query
            ->where('record_type', $record::class)
            ->where('record_id', (string) $record->getKey());
    }
}
