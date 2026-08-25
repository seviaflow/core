<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('permission_checker_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('seviaflow.resources', [
        'users' => PermissionCheckerUserResource::class,
        'orders' => PermissionCheckerOrderResource::class,
    ]);
});

it('permissions disabled does not deny', function (): void {
    config()->set('seviaflow.permissions.enabled', false);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Disabled User']);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW_ANY))->toBeTrue();
});

it('permissions enabled denies user with no roles', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'No Roles']);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW_ANY))->toBeFalse();
});

it('role with permission grants ability', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Granted']);
    $role = Role::query()->create(['name' => 'User Reader', 'slug' => 'user-reader']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'users',
        'ability' => PermissionAbilities::VIEW_ANY,
        'allowed' => true,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW_ANY))->toBeTrue();
});

it('multiple roles grant ability if any role allows', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Multi Role']);

    $roleWithoutPermission = Role::query()->create(['name' => 'No Grant', 'slug' => 'no-grant']);
    $roleWithPermission = Role::query()->create(['name' => 'Grant', 'slug' => 'grant']);

    RoleAssignment::query()->create([
        'role_id' => $roleWithoutPermission->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    RoleAssignment::query()->create([
        'role_id' => $roleWithPermission->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $roleWithPermission->id,
        'resource' => 'users',
        'ability' => PermissionAbilities::UPDATE,
        'allowed' => true,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::UPDATE))->toBeTrue();
});

it('missing permission denies when enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Missing Permission']);
    $role = Role::query()->create(['name' => 'Role Only', 'slug' => 'role-only']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::DELETE))->toBeFalse();
});

it('wrong resource denies', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Wrong Resource']);
    $role = Role::query()->create(['name' => 'Orders Viewer', 'slug' => 'orders-viewer']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'orders',
        'ability' => PermissionAbilities::VIEW_ANY,
        'allowed' => true,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW_ANY))->toBeFalse();
});

it('wrong ability denies', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Wrong Ability']);
    $role = Role::query()->create(['name' => 'User Creator', 'slug' => 'user-creator']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'users',
        'ability' => PermissionAbilities::CREATE,
        'allowed' => true,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::DELETE))->toBeFalse();
});

it('allowed false does not grant ability', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Denied']);
    $role = Role::query()->create(['name' => 'Denied Role', 'slug' => 'denied-role']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'users',
        'ability' => PermissionAbilities::VIEW,
        'allowed' => false,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW))->toBeFalse();
});

it('uses resource slug consistently and can normalize mapped resource class', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $checker = new PermissionChecker;
    $user = PermissionCheckerUser::query()->create(['name' => 'Slug User']);
    $role = Role::query()->create(['name' => 'Slug Role', 'slug' => 'slug-role']);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'users',
        'ability' => PermissionAbilities::VIEW_ANY,
        'allowed' => true,
    ]);

    expect($checker->allows($user, 'users', PermissionAbilities::VIEW_ANY))->toBeTrue()
        ->and($checker->allows($user, PermissionCheckerUserResource::class, PermissionAbilities::VIEW_ANY))->toBeTrue();
});

final class PermissionCheckerUser extends Model
{
    protected $table = 'permission_checker_users';

    protected $guarded = [];
}

final class PermissionCheckerUserResource {}

final class PermissionCheckerOrderResource {}
