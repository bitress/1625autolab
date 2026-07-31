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
        Schema::create('faqs', function (Blueprint $table) {
            $table->increments('id');
            $table->text('question');
            $table->text('answer');
            $table->string('category', 200)->default('General');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('faqs')->insertOrIgnore([
            ['question' => 'What services does 1625 Autolab offer?', 'answer' => 'We specialize in headlight retrofits, Android headunits, advanced security systems, and aesthetic upgrades including custom grilles, ambient lighting, and vinyl wraps.', 'category' => 'General', 'sort_order' => 0],
            ['question' => 'How long does a headlight retrofit take?', 'answer' => 'A standard headlight retrofit typically takes 4–6 hours depending on the vehicle and complexity of the build.', 'category' => 'Services', 'sort_order' => 1],
            ['question' => 'Do you offer a warranty on your work?', 'answer' => 'Yes, all our installations come with a workmanship warranty. Parts warranties vary by manufacturer. Contact us for specific warranty details.', 'category' => 'General', 'sort_order' => 2],
            ['question' => 'How do I book an appointment?', 'answer' => 'You can book an appointment directly through our website using the Book Appointment button, or call us at 0939 330 8263.', 'category' => 'Booking', 'sort_order' => 3],
            ['question' => 'What payment methods do you accept?', 'answer' => 'We accept cash, bank transfer, and major e-wallets (GCash, Maya). Payment details will be confirmed upon booking.', 'category' => 'Billing', 'sort_order' => 4],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
