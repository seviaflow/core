<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class AdminAccessGate
{
    public function allows(?Authenticatable $user = null): bool
    {
        $actor = $user ?? Auth::user();
        if (! $actor instanceof Authenticatable) {
            return false;
        }

        $callback = config('seviaflow.permissions.admin_gate');
        if (! is_callable($callback)) {
            return false;
        }

        try {
            return (bool) call_user_func($callback, $actor);
        } catch (Throwable) {
            return false;
        }
    }
}
