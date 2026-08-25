<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seviaflow_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('seviaflow_roleables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('seviaflow_roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->index(['model_type', 'model_id'], 'seviaflow_roleables_model_lookup');
            $table->unique(['role_id', 'model_type', 'model_id'], 'seviaflow_roleables_unique_assignment');
        });

        Schema::create('seviaflow_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('seviaflow_roles')->cascadeOnDelete();
            $table->string('resource');
            $table->string('ability');
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'resource', 'ability'], 'seviaflow_permissions_role_resource_ability_unique');
            $table->index(['resource', 'ability'], 'seviaflow_permissions_resource_ability_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seviaflow_permissions');
        Schema::dropIfExists('seviaflow_roleables');
        Schema::dropIfExists('seviaflow_roles');
    }
};
