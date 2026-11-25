<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(
                ['store_id', 'is_available'],
                'products_store_available_index'
            );

            $table->index(
                ['category_id', 'is_available'],
                'products_category_available_index'
            );

            $table->index(
                ['is_available', 'sort_order', 'id'],
                'products_marketplace_sort_index'
            );

            $table->index(
                ['is_available', 'sales_count', 'id'],
                'products_popular_index'
            );

            $table->index(
                ['is_available', 'price'],
                'products_price_index'
            );
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->index(
                ['is_active', 'is_open'],
                'stores_marketplace_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_store_available_index');
            $table->dropIndex('products_category_available_index');
            $table->dropIndex('products_marketplace_sort_index');
            $table->dropIndex('products_popular_index');
            $table->dropIndex('products_price_index');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_marketplace_status_index');
        });
    }
};