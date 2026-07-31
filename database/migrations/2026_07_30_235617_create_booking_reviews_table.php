<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->string('booking_id', 36)->unique('uq_booking_review');
            $table->unsignedInteger('user_id')->index('idx_booking_reviews_user');
            $table->unsignedTinyInteger('rating')->index('idx_booking_reviews_rating');
            $table->text('review')->nullable();
            $table->boolean('is_approved')->default(false)->index('idx_booking_reviews_approved');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::statement('ALTER TABLE booking_reviews ADD CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_reviews');
    }
};
