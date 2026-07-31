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
            $table->string('build_slug', 255)->nullable()->after('source')->index('idx_bookings_build_slug');
        });

        $bookings = DB::table('bookings')
            ->where('status', 'completed')
            ->whereNotNull('reference_number')
            ->where('reference_number', '!=', '')
            ->where(function($query) {
                $query->whereNull('build_slug')
                      ->orWhere('build_slug', '');
            })
            ->get();

        foreach ($bookings as $b) {
            $ref = strtolower($b->reference_number);
            $portfolio = DB::table('portfolio')
                ->where('is_active', 1)
                ->where(function ($query) use ($ref) {
                    $query->whereRaw("LOWER(COALESCE(title, '') || ' ' || COALESCE(description, '')) LIKE ?", ['%' . $ref . '%']);
                })
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($portfolio && !empty($portfolio->title)) {
                $slug = \Illuminate\Support\Str::slug($portfolio->title);
                DB::table('bookings')->where('id', $b->id)->update(['build_slug' => $slug]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_build_slug');
            $table->dropColumn('build_slug');
        });
    }
};
