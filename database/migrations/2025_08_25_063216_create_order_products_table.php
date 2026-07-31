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
        Schema::create('order_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->decimal('price')->nullable();
            $table->decimal('sale_price',8,2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_thumbnail_url')->nullable();
            $table->decimal('single_price',8,2)->nullable();
            $table->decimal('shipping_cost',8,2)->nullable();
            $table->decimal('tax',8,2)->nullable();
            $table->decimal('subtotal',8,2)->nullable();
            $table->string('refund_status')->nullable();
            $table->string('eta')->nullable();
            $table->string('status')->default('--');
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
