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
        Schema::create('customer_inquiries', function (Blueprint $table) {
            $table->char('id', 32)->primary();
            $table->char('user_id', 36)->nullable()->default(null)->index('idx_customer_inquiries_user_id');
            $table->string('full_name', 200);
            $table->text('address');
            $table->string('contact_number', 50);
            $table->string('plate_number', 50)->nullable();
            $table->string('email_address', 255);
            $table->string('facebook_name', 255);
            $table->string('make', 100);
            $table->string('model', 100);
            $table->string('year_model', 20);
            $table->text('product_to_purchase');
            $table->date('appointment_date')->index('idx_inquiries_appointment_date');
            $table->string('appointment_time', 20)->index('idx_inquiries_appointment_time');
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending')->index('idx_inquiries_status');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('inquiry_slot_occupancy', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('inquiry_id', 36)->unique();
            $table->date('appointment_date');
            $table->string('appointment_time', 20);
            $table->string('status', 30)->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['appointment_date', 'appointment_time'], 'idx_inquiry_slot_occupancy_date_time');
        });

        Schema::create('inquiry_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->char('inquiry_id', 32)->index('idx_inquiry_activity_logs_inquiry_id');
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->enum('actor_role', ['system', 'admin', 'client'])->default('system');
            $table->string('event_type', 50);
            $table->string('action', 255);
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_inquiry_activity_logs_created_at');

            $table->foreign('inquiry_id')->references('id')->on('customer_inquiries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiry_activity_logs');
        Schema::dropIfExists('inquiry_slot_occupancy');
        Schema::dropIfExists('customer_inquiries');
    }
};
