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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference_number', 20)->nullable()->after('id');
        });

        // Use PHP to generate reference numbers for SQLite compatibility
        $bookings = DB::table('bookings')->whereNull('reference_number')->orWhere('reference_number', '')->get();
        foreach ($bookings as $booking) {
            $ref = 'BK-LEG-'.strtoupper(substr(str_replace('-', '', $booking->id), 0, 10));
            DB::table('bookings')->where('id', $booking->id)->update(['reference_number' => $ref]);
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference_number', 20)->nullable(false)->change();
            $table->unique('reference_number', 'idx_reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_reference_number');
            $table->dropColumn('reference_number');
        });
    }
};
