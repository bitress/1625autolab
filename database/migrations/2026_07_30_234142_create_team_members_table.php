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
        Schema::create('team_members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->string('role', 200)->default('');
            $table->text('image_url')->nullable();
            $table->text('bio')->nullable();
            $table->text('full_bio')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('facebook', 500)->nullable();
            $table->string('instagram', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('team_members')->insertOrIgnore([
            [
                'name' => 'Alex "The Spark" Mercer',
                'role' => 'Master Retrofitter & Founder',
                'image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=1974&auto=format&fit=crop',
                'bio' => 'With over 15 years of experience in automotive electronics, Alex founded 1625 Auto Lab to push the boundaries of custom lighting.',
                'full_bio' => "Alex started his journey in his parents' garage, fixing broken headlight seals and upgrading halogen bulbs. His obsession with perfection led him to master projector retrofitting and custom LED integration. Today, he oversees all major builds at 1625 Auto Lab, ensuring every vehicle leaves with a signature look and flawless functionality.",
                'email' => 'alex@1625autolab.com',
                'phone' => '0939 330 8263',
                'sort_order' => 0,
            ],
            [
                'name' => 'Sarah Chen',
                'role' => 'Lead Technician',
                'image_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=1976&auto=format&fit=crop',
                'bio' => 'Specializing in Android headunit integrations and complex wiring harnesses. Sarah ensures every install looks factory-perfect.',
                'full_bio' => 'Sarah holds a degree in Electrical Engineering and brings a meticulous approach to automotive wiring. She specializes in integrating modern Android headunits into older vehicles, ensuring steering wheel controls, backup cameras, and factory amplifiers work seamlessly. Her wiring harnesses are known for being cleaner than OEM.',
                'email' => 'sarah@1625autolab.com',
                'phone' => '0939 330 8264',
                'sort_order' => 1,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
