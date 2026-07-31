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
        Schema::create('shop_hours', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->unsignedTinyInteger('day_of_week')->unique()->comment('0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat');
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->default('09:00:00');
            $table->time('close_time')->default('18:00:00');
            $table->unsignedTinyInteger('slot_interval_h')->default(2)->comment('Appointment slot interval in hours (1, 2, or 3)');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Seed default hours: Mon-Sat open 09:00-18:00, 2h slots; Sunday closed.
        DB::table('shop_hours')->upsert([
            ['day_of_week' => 0, 'is_open' => false, 'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 1, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 2, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 3, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 4, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 5, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
            ['day_of_week' => 6, 'is_open' => true,  'open_time' => '09:00:00', 'close_time' => '18:00:00', 'slot_interval_h' => 2],
        ], ['day_of_week'], ['is_open', 'open_time', 'close_time', 'slot_interval_h']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_hours');
    }
};
