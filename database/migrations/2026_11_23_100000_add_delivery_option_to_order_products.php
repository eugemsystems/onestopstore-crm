<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'delivery_option_id')) {
                $table->unsignedBigInteger('delivery_option_id')->nullable();
            }
            if (!Schema::hasColumn('order_products', 'delivery_option_label')) {
                $table->string('delivery_option_label')->nullable();
            }
            if (!Schema::hasColumn('order_products', 'delivery_option_text')) {
                $table->string('delivery_option_text')->nullable();
            }
            if (!Schema::hasColumn('order_products', 'delivery_option_price')) {
                $table->decimal('delivery_option_price', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            foreach (['delivery_option_id', 'delivery_option_label', 'delivery_option_text', 'delivery_option_price'] as $c) {
                if (Schema::hasColumn('order_products', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
