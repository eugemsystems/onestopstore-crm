<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'fast_shipping_cost')) {
                $table->decimal('fast_shipping_cost', 8, 2)->default(0)->after('shipping_cost');
            }
            if (!Schema::hasColumn('order_products', 'item_shipping_method')) {
                $table->string('item_shipping_method')->nullable()->after('fast_shipping_cost');
            }
            if (!Schema::hasColumn('order_products', 'has_fast_shipping')) {
                $table->boolean('has_fast_shipping')->default(false)->after('item_shipping_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (Schema::hasColumn('order_products', 'has_fast_shipping')) {
                $table->dropColumn('has_fast_shipping');
            }
            if (Schema::hasColumn('order_products', 'item_shipping_method')) {
                $table->dropColumn('item_shipping_method');
            }
            if (Schema::hasColumn('order_products', 'fast_shipping_cost')) {
                $table->dropColumn('fast_shipping_cost');
            }
        });
    }
};

