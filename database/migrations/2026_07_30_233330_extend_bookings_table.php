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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('vehicle_make', 100)->nullable()->after('vehicle_info');
            $table->string('vehicle_model', 100)->nullable()->after('vehicle_make');
            $table->string('vehicle_year', 4)->nullable()->after('vehicle_model');
            $table->text('service_ids')->nullable()->after('service_id');
            $table->mediumText('signature_data')->nullable()->after('notes');
            $table->text('media_urls')->nullable()->after('signature_data');
            $table->text('parts_notes')->nullable()->after('status');
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'vehicle_make',
                'vehicle_model',
                'vehicle_year',
                'service_ids',
                'signature_data',
                'media_urls',
                'parts_notes',
            ]);
            $table->string('status')->default('pending')->change();
        });
    }
};
