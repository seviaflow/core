<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Auth\Roles;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class AdminUserModelResolver
{
    /**
     * @return class-string<Model>
     */
    public function resolve(): string
    {
        /** @var mixed $configured */
        $configured = config('seviaflow.permissions.user_model');

        if (is_string($configured) && $configured !== '') {
            return $this->assertModelClass($configured);
        }

        $guard = (string) config('auth.defaults.guard', 'web');
        $provider = (string) config('auth.guards.'.$guard.'.provider', config('auth.defaults.provider', 'users'));
        /** @var mixed $providerModel */
        $providerModel = config('auth.providers.'.$provider.'.model');

        if (is_string($providerModel) && $providerModel !== '') {
            return $this->assertModelClass($providerModel);
        }

        throw new RuntimeException('Unable to resolve admin user model for role assignments.');
    }

    /**
     * @param  class-string  $candidate
     * @return class-string<Model>
     */
    private function assertModelClass(string $candidate): string
    {
        if (! class_exists($candidate) || ! is_subclass_of($candidate, Model::class)) {
            throw new RuntimeException(sprintf('Configured admin user model [%s] is not a valid Eloquent model.', $candidate));
        }

        /** @var class-string<Model> $candidate */
        return $candidate;
    }
}
