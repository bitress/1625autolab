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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('site_settings')->insertOrIgnore([
            ['key' => 'company_description_1', 'value' => "Founded in 2018, 1625 Auto Lab started as a small garage operation focused on fixing poorly done headlight retrofits. Today, we are Los Angeles' premier destination for high-end automotive electronics and premium vehicle upgrades."],
            ['key' => 'company_description_2', 'value' => 'We believe that your vehicle is an extension of your personality. Our mission is to provide unparalleled craftsmanship, using only the highest quality components, to turn your automotive vision into reality.'],
            ['key' => 'about_heading', 'value' => 'Built on Precision. Driven by Passion.'],
            ['key' => 'about_image_url', 'value' => 'https://images.unsplash.com/photo-1632823471565-1ec2a74b45b4?q=80&w=2070&auto=format&fit=crop'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
