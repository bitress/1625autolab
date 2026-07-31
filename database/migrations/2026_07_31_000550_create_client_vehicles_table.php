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
        Schema::create('client_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index('idx_client_vehicles_user');
            $table->string('make', 120);
            $table->string('model', 120);
            $table->string('year', 10);
            $table->string('image_url', 255)->nullable();
            $table->string('vin', 64)->nullable();
            $table->string('license_plate', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id', 'fk_client_vehicles_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_vehicles');
    }
};
