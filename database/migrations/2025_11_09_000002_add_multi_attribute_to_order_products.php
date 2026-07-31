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
            // Add columns to store multi-attribute variation data
            $table->json('selected_attribute_ids')->nullable()->after('variation_id');
            $table->string('variation_display_name', 255)->nullable()->after('selected_attribute_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn(['selected_attribute_ids', 'variation_display_name']);
        });
    }
};

