<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 100)->default('default');
            $table->text('description');
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 120)->nullable();
            $table->string('causer_type', 120)->nullable();
            $table->string('causer_id', 120)->nullable();
            $table->json('properties_json')->nullable();
            $table->json('attribute_changes_json')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_activity_logs_created_at');

            $table->index(['subject_type', 'subject_id'], 'idx_activity_logs_subject');
            $table->index(['causer_type', 'causer_id'], 'idx_activity_logs_causer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
