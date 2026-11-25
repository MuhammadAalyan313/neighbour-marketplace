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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('checkout_batch_id')
                ->constrained('checkout_batches')
                ->cascadeOnDelete();

            $table->string('order_number', 80)
                ->unique();

            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();

            /*
             * Buyer snapshots.
             */
            $table->string('buyer_name', 150);

            $table->string('buyer_email', 255)
                ->nullable();

            $table->string('buyer_house_number', 100);

            $table->string('buyer_phone', 30);

            $table->decimal('subtotal', 12, 2)
                ->default(0);

            $table->decimal('discount_amount', 12, 2)
                ->default(0);

            $table->decimal('delivery_fee', 12, 2)
                ->default(0);

            /*
             * Current business model = 0.
             * Future commission can be enabled without
             * rewriting historical orders.
             */
            $table->decimal('platform_fee', 12, 2)
                ->default(0);

            $table->decimal('platform_fee_rate', 8, 4)
                ->default(0);

            $table->decimal('total_amount', 12, 2)
                ->default(0);

            $table->enum('fulfillment_type', [
                'pickup',
                'seller_delivery',
            ])->default('seller_delivery');

            $table->enum('status', [
                'pending',
                'accepted',
                'delivered',
                'cancelled',
            ])->default('pending');

            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('unpaid');

            $table->string('payment_method', 50)
                ->nullable();

            $table->string('payment_reference', 150)
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamp('accepted_at')
                ->nullable();

            $table->timestamp('delivered_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->text('cancellation_reason')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'checkout_batch_id',
                'store_id',
            ]);

            $table->index([
                'store_id',
                'status',
                'created_at',
            ]);

            $table->index([
                'buyer_id',
                'created_at',
            ]);

            $table->index([
                'payment_status',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
