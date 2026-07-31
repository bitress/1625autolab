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
        if (\Illuminate\Support\Facades\Schema::hasTable('marketing_campaigns')) {
            Schema::table('marketing_campaigns', function (Blueprint $table) {
                $table->string('category', 120)->nullable()->default(null)->after('name');
                $table->mediumText('message_html')->nullable()->after('message');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn(['category', 'message_html']);
        });
    }
};
