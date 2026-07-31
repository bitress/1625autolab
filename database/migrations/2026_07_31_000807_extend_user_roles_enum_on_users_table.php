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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Down migration can restore it to the previous state, but we'll leave it empty to avoid data truncation issues on rollback.
    }
};
