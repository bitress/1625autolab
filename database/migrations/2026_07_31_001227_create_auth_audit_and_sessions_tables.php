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
        Schema::create('auth_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable()->index('idx_auth_audit_user_id');
            $table->string('email', 255)->default('')->index('idx_auth_audit_email');
            $table->string('ip_address', 64)->default('')->index('idx_auth_audit_ip');
            $table->string('user_agent', 500)->default('');
            $table->string('event_type', 64)->index('idx_auth_audit_event_type');
            $table->enum('outcome', ['success', 'failure', 'blocked', 'warning'])->default('success');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_auth_audit_created_at');

            $table->foreign('user_id', 'fk_auth_audit_user')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index('idx_auth_sessions_user_id');
            $table->char('token_hash', 64)->unique('uq_auth_sessions_token_hash');
            $table->string('ip_address', 64)->default('');
            $table->string('user_agent', 500)->default('');
            $table->timestamp('issued_at')->useCurrent();
            $table->dateTime('expires_at')->index('idx_auth_sessions_expires_at');
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('revoked_at')->nullable()->index('idx_auth_sessions_revoked_at');
            $table->string('revoked_reason', 80)->nullable();

            $table->foreign('user_id', 'fk_auth_sessions_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('auth_audit_logs');
    }
};
