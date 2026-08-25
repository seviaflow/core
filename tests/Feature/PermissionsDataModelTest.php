<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('permission_admin_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('creates roles table via package migrations', function (): void {
    expect(Schema::hasTable('seviaflow_roles'))->toBeTrue();
});

it('creates permissions table via package migrations', function (): void {
    expect(Schema::hasTable('seviaflow_permissions'))->toBeTrue();
});

it('creates roleables table via package migrations', function (): void {
    expect(Schema::hasTable('seviaflow_roleables'))->toBeTrue();
});

it('role can have permissions', function (): void {
    $role = Role::query()->create([
        'name' => 'Support Operator',
        'slug' => 'support-operator',
    ]);

    $permission = Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'users',
        'ability' => 'viewAny',
        'allowed' => true,
    ]);

    expect($role->permissions)->toHaveCount(1)
        ->and($role->permissions->first()?->is($permission))->toBeTrue();
});

it('role can be assigned to a model through roleables', function (): void {
    $role = Role::query()->create([
        'name' => 'Admin Manager',
        'slug' => 'admin-manager',
    ]);

    $admin = PermissionAdminUser::query()->create([
        'name' => 'Alice Admin',
    ]);

    $assignment = RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $admin::class,
        'model_id' => $admin->getKey(),
    ]);

    expect($role->assignments)->toHaveCount(1)
        ->and($role->assignments->first()?->is($assignment))->toBeTrue()
        ->and($assignment->model?->is($admin))->toBeTrue()
        ->and($assignment->role?->is($role))->toBeTrue();
});

it('casts permission allowed as boolean', function (): void {
    $role = Role::query()->create([
        'name' => 'Billing Viewer',
        'slug' => 'billing-viewer',
    ]);

    $permission = Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'orders',
        'ability' => 'view',
        'allowed' => 1,
    ]);

    expect($permission->allowed)->toBeBool()->toBeTrue();
});

it('enforces unique role slug', function (): void {
    Role::query()->create([
        'name' => 'Operator',
        'slug' => 'operator',
    ]);

    expect(fn () => Role::query()->create([
        'name' => 'Another Operator',
        'slug' => 'operator',
    ]))->toThrow(QueryException::class);
});

it('permissions config defaults to disabled', function (): void {
    expect(config('seviaflow.permissions.enabled'))->toBeFalse();
});

final class PermissionAdminUser extends Model
{
    protected $table = 'permission_admin_users';

    protected $guarded = [];
}
