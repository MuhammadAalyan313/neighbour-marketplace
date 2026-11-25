<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        Schema::table('orders', function (Blueprint $table) {
            if (! $this->indexExists(
                'orders',
                'orders_store_status_created_index'
            )) {
                $table->index(
                    ['store_id', 'status', 'created_at'],
                    'orders_store_status_created_index'
                );
            }

            if (! $this->indexExists(
                'orders',
                'orders_buyer_created_index'
            )) {
                $table->index(
                    ['buyer_id', 'created_at'],
                    'orders_buyer_created_index'
                );
            }

            if (! $this->indexExists(
                'orders',
                'orders_checkout_batch_index'
            )) {
                $table->index(
                    ['checkout_batch_id'],
                    'orders_checkout_batch_index'
                );
            }

            if (! $this->indexExists(
                'orders',
                'orders_payment_status_created_index'
            )) {
                $table->index(
                    ['payment_status', 'created_at'],
                    'orders_payment_status_created_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */

        Schema::table('order_items', function (Blueprint $table) {
            if (! $this->indexExists(
                'order_items',
                'order_items_order_product_index'
            )) {
                $table->index(
                    ['order_id', 'product_id'],
                    'order_items_order_product_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Reviews
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Your reviews table uses is_visible, not is_published.
        |
        */
Schema::table('reviews', function (Blueprint $table) {
    if (! $this->indexExists(
        'reviews',
        'reviews_store_id_is_published_created_at_index'
    )) {
        $table->index(
            ['store_id', 'is_published', 'created_at'],
            'reviews_store_id_is_published_created_at_index'
        );
    }
});
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists(
                'orders',
                'orders_store_status_created_index'
            )) {
                $table->dropIndex(
                    'orders_store_status_created_index'
                );
            }

            if ($this->indexExists(
                'orders',
                'orders_buyer_created_index'
            )) {
                $table->dropIndex(
                    'orders_buyer_created_index'
                );
            }

            if ($this->indexExists(
                'orders',
                'orders_checkout_batch_index'
            )) {
                $table->dropIndex(
                    'orders_checkout_batch_index'
                );
            }

            if ($this->indexExists(
                'orders',
                'orders_payment_status_created_index'
            )) {
                $table->dropIndex(
                    'orders_payment_status_created_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */

        Schema::table('order_items', function (Blueprint $table) {
            if ($this->indexExists(
                'order_items',
                'order_items_order_product_index'
            )) {
                $table->dropIndex(
                    'order_items_order_product_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Reviews
        |--------------------------------------------------------------------------
        */

     Schema::table('reviews', function (Blueprint $table) {
    if ($this->indexExists(
        'reviews',
        'reviews_store_id_is_published_created_at_index'
    )) {
        $table->dropIndex(
            'reviews_store_id_is_published_created_at_index'
        );
    }
});
    }

    /*
    |--------------------------------------------------------------------------
    | Check If Index Exists
    |--------------------------------------------------------------------------
    */

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $existingIndex) {
            if (
                isset($existingIndex['name'])
                && $existingIndex['name'] === $index
            ) {
                return true;
            }
        }

        return false;
    }
};