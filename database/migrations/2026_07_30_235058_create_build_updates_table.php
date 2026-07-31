<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('build_updates', function (Blueprint $table) {
            $table->increments('id');
            $table->char('booking_id', 36);
            $table->text('note')->nullable();
            $table->text('photo_urls')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('booking_id', 'fk_build_updates_booking')
                ->references('id')
                ->on('bookings')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_updates');
    }
};
