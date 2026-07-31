<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_variations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('service_id');
            $table->string('name', 255);
            $table->text('description')->default('');
            $table->string('price', 100)->default('');
            $table->text('images')->default('[]');
            $table->text('specs')->default('[]');
            $table->smallInteger('sort_order')->default(0);

            $table->foreign('service_id', 'fk_svar_service')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_variations');
    }
};
