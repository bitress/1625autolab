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
        Schema::create('offers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 300)->default('');
            $table->string('subtitle', 300)->default('');
            $table->text('description')->default('');
            $table->string('badge_text', 100)->default('Limited Time Offer');
            $table->string('cta_text', 100)->default('Claim Your Offer');
            $table->string('cta_url', 500)->default('#contact');
            $table->unsignedInteger('linked_service_id')->nullable();
            $table->unsignedInteger('linked_product_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
