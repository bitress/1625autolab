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
        Schema::create('token_blocklist', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token_hash', 64)->unique('uq_token_blocklist_hash');
            $table->timestamp('expires_at')->index('idx_token_blocklist_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_blocklist');
    }
};
