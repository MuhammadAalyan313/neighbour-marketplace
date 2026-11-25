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
       Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            /*
             * Historical snapshot.
             */
            $table->string('product_title', 180);

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 12, 2);

            $table->decimal('subtotal', 12, 2);

            /*
             * Future per-item commission.
             */
            $table->decimal('platform_fee', 12, 2)
                ->default(0);

            $table->decimal('platform_fee_rate', 8, 4)
                ->default(0);

            $table->timestamps();

            $table->index([
                'order_id',
                'product_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
