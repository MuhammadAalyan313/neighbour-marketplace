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
         Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_subscription_id')
                ->constrained('store_subscriptions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->char('currency', 3)
                ->default('PKR');

            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('pending');

            $table->string('payment_method', 50)
                ->nullable();

            $table->string('provider', 50)
                ->nullable();

            $table->string('provider_transaction_id', 150)
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'store_subscription_id',
                'status',
            ]);

            $table->index('provider_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
