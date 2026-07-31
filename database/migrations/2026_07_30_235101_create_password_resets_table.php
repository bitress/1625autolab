<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->increments('id');
                $table->string('email', 255)->index('idx_password_resets_email');
                $table->char('token', 64)->unique('uq_password_resets_token');
                $table->timestamp('expires_at')->index('idx_password_resets_expires');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
