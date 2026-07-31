<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'exchange_rate')) {
                // store the rate used to convert from base currency to order currency
                $table->decimal('exchange_rate', 12, 6)->nullable()->after('currency_symbol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
        });
    }
};
