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
        Schema::create('booking_waitlist', function (Blueprint $table) {
            $table->increments('id');
            $table->date('slot_date');
            $table->string('slot_time', 20);
            $table->unsignedInteger('user_id')->nullable()->comment('NULL for guest waitlists');
            $table->string('name', 200)->default('');
            $table->string('email', 255)->default('');
            $table->string('phone', 30)->default('');
            $table->text('service_ids')->comment('comma-separated');
            $table->text('notes')->nullable();
            $table->enum('status', ['waiting', 'notified', 'booked', 'expired'])->default('waiting');
            $table->timestamp('notified_at')->nullable()->default(null);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['slot_date', 'slot_time'], 'idx_bwl_slot');
            $table->index('status', 'idx_bwl_status');
            $table->index('user_id', 'idx_bwl_user');

            // Optionally, add a foreign key if users table exists:
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_waitlist');
    }
};
