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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();

            $table->boolean('email_new_booking')->default(true);
            $table->boolean('email_status_changed')->default(true);
            $table->boolean('email_build_update')->default(true);
            $table->boolean('email_parts_update')->default(true);

            $table->boolean('inapp_status_changed')->default(true);
            $table->boolean('inapp_build_update')->default(true);
            $table->boolean('inapp_parts_update')->default(true);

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
