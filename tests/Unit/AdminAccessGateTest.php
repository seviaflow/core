<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use SeviaFlow\Core\Auth\AdminAccessGate;

it('fails closed when no host admin gate is configured', function (): void {
    $user = new AdminAccessGateTestUser;

    config()->set('seviaflow.permissions.admin_gate', null);

    expect((new AdminAccessGate)->allows($user))->toBeFalse();
});

it('delegates admin access to the configured host gate', function (): void {
    $user = new AdminAccessGateTestUser;
    config()->set('seviaflow.permissions.admin_gate', static fn (Authenticatable $actor): bool => $actor === $user);

    expect((new AdminAccessGate)->allows($user))->toBeTrue()
        ->and((new AdminAccessGate)->allows(new AdminAccessGateTestUser))->toBeFalse();
});

final class AdminAccessGateTestUser implements Authenticatable
{
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return 'password';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
