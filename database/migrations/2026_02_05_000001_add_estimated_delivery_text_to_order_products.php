<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'estimated_delivery_text')) {
                $table->string('estimated_delivery_text')->nullable()->after('eta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (Schema::hasColumn('order_products', 'estimated_delivery_text')) {
                $table->dropColumn('estimated_delivery_text');
            }
        });
    }
};
