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
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v4');
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('name', 200);
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('vehicle_info', 255);
            $table->integer('service_id')->unsigned();
            $table->date('appointment_date');
            $table->string('appointment_time', 20);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id', 'fk_bookings_user')->references('id')->on('users')->onDelete('set null');
            $table->foreign('service_id', 'fk_bookings_service')->references('id')->on('services')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
