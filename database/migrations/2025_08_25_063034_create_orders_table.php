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

        Schema::create('order_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->integer('sequence')->nullable();
            $table->bigInteger('created_by_id')->unsigned()->nullable();
            $table->integer('status')->default(1);
            $table->integer('system_reserve')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {

            $table->unsignedBigInteger('id')->primary();
            $table->integer('order_number')->unique()->nullable();
            $table->unsignedBigInteger('consumer_id');
            $table->string('consumer_name')->nullable();
            $table->string('consumer_country_code')->nullable();
            $table->string('consumer_phone_number')->nullable();
            $table->string('consumer_email')->nullable();
            $table->decimal('tax_total',8,2)->nullable();
            $table->decimal('shipping_total',8,2)->nullable();
            $table->decimal('delivery_price',8,2)->nullable();
            $table->decimal('points_amount',8,2)->nullable();
            $table->decimal('wallet_balance',8,2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount_total', 12, 2)->nullable();
            $table->decimal('amount',8,2)->nullable();
            $table->decimal('total',8,2)->nullable();
            $table->decimal('coupon_total_discount',8,2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('order_status')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('delivery_description')->nullable();
            $table->string('delivery_interval')->nullable();
            $table->unsignedBigInteger('order_status_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->bigInteger('created_by_id')->unsigned()->nullable();
            $table->string('invoice_url')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->jsonb('order_history')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
        Schema::dropIfExists('orders');
    }
};
