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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(true)->after('is_active');
            $table->integer('stock_qty')->default(0)->after('track_stock');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->boolean('track_stock')->default(true)->after('sort_order');
            $table->integer('stock_qty')->default(0)->after('track_stock');
        });

        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique('uq_product_orders_order_number');
            $table->unsignedInteger('user_id')->nullable()->index('idx_product_orders_user_id');
            $table->string('customer_name', 200);
            $table->string('customer_email', 255);
            $table->string('customer_phone', 30);
            $table->enum('fulfillment_type', ['courier', 'walk_in'])->default('courier');
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 120)->default('');
            $table->string('delivery_province', 120)->default('');
            $table->string('delivery_postal_code', 20)->default('');
            $table->enum('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled'])->default('pending')->index('idx_product_orders_status');
            $table->enum('payment_status', ['unpaid', 'paid', 'cod'])->default('unpaid');
            $table->string('courier_name', 120)->default('');
            $table->string('tracking_number', 120)->default('');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('shipping_fee', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent()->index('idx_product_orders_created_at');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id', 'fk_product_orders_user')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index('idx_product_order_items_order_id');
            $table->unsignedInteger('product_id')->index('idx_product_order_items_product_id');
            $table->unsignedInteger('variation_id')->nullable();
            $table->string('product_name', 255);
            $table->string('variation_name', 255)->default('');
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id', 'fk_product_order_items_order')->references('id')->on('product_orders')->onDelete('cascade');
            $table->foreign('product_id', 'fk_product_order_items_product')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variation_id', 'fk_product_order_items_variation')->references('id')->on('product_variations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
        Schema::dropIfExists('product_orders');

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'stock_qty']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'stock_qty']);
        });
    }
};
