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
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('page_id', 64)->unique();
            $table->string('page_name', 255)->default('');
            $table->text('page_access_token');
            $table->boolean('token_valid')->default(true)->index('idx_token_valid');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
