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
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 200);
            $table->text('description')->comment('Short card description');
            $table->text('full_description')->default('')->comment('Long detail-page description');
            $table->string('icon', 50)->default('Wrench')->comment('Lucide icon name (Lightbulb, MonitorPlay …)');
            $table->string('image_url', 500)->default('')->comment('Hero image URL');
            $table->string('duration', 80)->default('')->comment('e.g. 4-6 Hours');
            $table->string('starting_price', 80)->default('')->comment('e.g. ₱13,750');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
