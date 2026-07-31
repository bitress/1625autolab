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
        Schema::create('shop_closed_dates', function (Blueprint $table) {
            $table->increments('id');
            $table->date('closed_date')->unique()->comment('The calendar date that is closed');
            $table->string('reason', 255)->nullable()->comment('Optional label shown to clients');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_closed_dates');
    }
};
