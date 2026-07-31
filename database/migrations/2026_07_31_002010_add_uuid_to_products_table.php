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
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill existing rows with a UUID
        $products = DB::table('products')->whereNull('uuid')->orWhere('uuid', '')->get();
        foreach ($products as $p) {
            DB::table('products')->where('id', $p->id)->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }

        // Enforce NOT NULL and add unique constraint
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid', 'uq_products_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('uq_products_uuid');
            $table->dropColumn('uuid');
        });
    }
};
