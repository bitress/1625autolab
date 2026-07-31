<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable()->index('idx_notifications_user');
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('message');
            $table->text('data')->nullable();
            $table->boolean('is_read')->default(false)->index('idx_notifications_is_read');
            $table->timestamp('created_at')->useCurrent()->index('idx_notifications_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
