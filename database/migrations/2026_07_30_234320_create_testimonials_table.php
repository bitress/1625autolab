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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->string('role', 200)->default('');
            $table->text('content');
            $table->tinyInteger('rating')->default(5);
            $table->text('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('testimonials')->insertOrIgnore([
            [
                'name' => 'Mark Reyes',
                'role' => 'Honda Civic FD Owner',
                'content' => '1625 Autolab completely transformed my Civic! The X1 Bi-LED Projector Headlights are incredibly bright and the amber demon eyes look aggressive. Highly recommend their services.',
                'rating' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150&h=150',
                'sort_order' => 0,
            ],
            [
                'name' => 'Sarah Mendoza',
                'role' => 'Toyota Fortuner Owner',
                'content' => 'Professional team and excellent workmanship. They installed a new suspension system on my Fortuner and the ride quality is night and day. Will definitely come back for more upgrades.',
                'rating' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150&h=150',
                'sort_order' => 1,
            ],
            [
                'name' => 'John Villanueva',
                'role' => 'Honda BR-V Owner',
                'content' => 'Got the full setup for my BR-V. The tri-color foglights are a game changer for night driving, especially during heavy rain. Great customer service from start to finish.',
                'rating' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=150&h=150',
                'sort_order' => 2,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
