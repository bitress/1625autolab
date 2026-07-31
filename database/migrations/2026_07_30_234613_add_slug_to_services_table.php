<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('slug', 255)->default('')->after('title');
        });

        // Backfill slugs
        $services = DB::table('services')->get();
        foreach ($services as $service) {
            DB::table('services')
                ->where('id', $service->id)
                ->update(['slug' => Str::slug($service->title)]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->unique('slug', 'uq_services_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('uq_services_slug');
            $table->dropColumn('slug');
        });
    }
};
