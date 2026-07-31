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
        Schema::table('service_variations', function (Blueprint $table) {
            $table->text('color_images')->default('{}')->after('colors');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->text('color_images')->default('{}')->after('colors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_variations', function (Blueprint $table) {
            $table->dropColumn('color_images');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('color_images');
        });
    }
};
