<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seviaflow_workflow_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('record_type');
            $table->string('record_id');
            $table->string('transition');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id'], 'seviaflow_workflow_activities_record_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seviaflow_workflow_activities');
    }
};
