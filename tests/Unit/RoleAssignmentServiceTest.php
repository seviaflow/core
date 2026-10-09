<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Auth\Roles\RoleAssignmentService;
use SeviaFlow\Core\Events\Auth\RoleAssignmentsChanged;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('role_assignment_service_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('publishes role changes after commit and not after rollback', function (): void {
    $user = RoleAssignmentServiceUser::query()->create(['name' => 'Operator']);
    $role = Role::query()->create(['name' => 'Reader', 'slug' => 'reader']);
    $events = [];
    Event::listen(RoleAssignmentsChanged::class, function (RoleAssignmentsChanged $event) use (&$events): void {
        $events[] = $event;
    });

    DB::transaction(function () use ($user, $role, &$events): void {
        (new RoleAssignmentService)->syncRoles($user, [$role->getKey()], ['tenant' => 'tenant-a']);
        expect(RoleAssignment::query()->where('model_id', $user->getKey())->exists())->toBeTrue();
        expect($events)->toBeEmpty();
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->user->is($user))->toBeTrue()
        ->and($events[0]->context['tenant'])->toBe('tenant-a');

    DB::beginTransaction();
    (new RoleAssignmentService)->syncRoles($user, []);
    DB::rollBack();

    expect($events)->toHaveCount(1)
        ->and(RoleAssignment::query()->where('model_id', $user->getKey())->exists())->toBeTrue();
});

final class RoleAssignmentServiceUser extends Model
{
    protected $table = 'role_assignment_service_users';

    protected $guarded = [];
}
