<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('stock_quantity')->nullable()->default(null);
            });
        }

        if (! Schema::hasColumn('order_items', 'stock_deducted')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->boolean('stock_deducted')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'stock_deducted')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('stock_deducted');
            });
        }

        // Do not drop stock_quantity here: it may predate this migration.
    }
};
