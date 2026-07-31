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
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'variation_name')) {
                $table->string('variation_name')->nullable()->after('variation_id');
            }
            if (!Schema::hasColumn('order_products', 'variation_attributes')) {
                $table->json('variation_attributes')->nullable()->after('variation_name');
            }
            if (!Schema::hasColumn('order_products', 'variation_sku')) {
                $table->string('variation_sku')->nullable()->after('variation_attributes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (Schema::hasColumn('order_products', 'variation_sku')) {
                $table->dropColumn('variation_sku');
            }
            if (Schema::hasColumn('order_products', 'variation_attributes')) {
                $table->dropColumn('variation_attributes');
            }
            if (Schema::hasColumn('order_products', 'variation_name')) {
                $table->dropColumn('variation_name');
            }
        });
    }
};
