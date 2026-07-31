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
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('email_verified_at')->nullable()->after('email');
        });

        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index('idx_email_verifications_user_id');
            $table->string('email', 255)->index('idx_email_verifications_email');
            $table->char('token', 64)->unique('uq_email_verifications_token');
            $table->dateTime('expires_at')->index('idx_email_verifications_expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_email_verifications_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
