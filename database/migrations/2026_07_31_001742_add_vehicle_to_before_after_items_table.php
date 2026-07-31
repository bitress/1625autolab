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
        Schema::table('before_after_items', function (Blueprint $table) {
            $table->string('vehicle_make', 100)->default('')->after('description');
            $table->string('vehicle_model', 100)->default('')->after('vehicle_make');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('before_after_items', function (Blueprint $table) {
            $table->dropColumn(['vehicle_make', 'vehicle_model']);
        });
    }
};
