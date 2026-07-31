<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            // Stores the API user ID of the assigned Staff Raines member.
            // Populated from the CRM ETA/Status popover and forwarded as
            // signed_by when the item is transferred to inventory shipments.
            $table->unsignedBigInteger('assigned_to')->nullable()->after('inventory_transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('assigned_to');
        });
    }
};
