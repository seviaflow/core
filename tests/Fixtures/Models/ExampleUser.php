<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ExampleUser extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
