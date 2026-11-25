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
       Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            $table->enum('status', [
                'trial',
                'active',
                'past_due',
                'cancelled',
                'expired',
            ])->default('active');

            $table->dateTime('starts_at');

            $table->dateTime('ends_at');

            $table->dateTime('cancelled_at')
                ->nullable();

            $table->string('provider', 50)
                ->nullable();

            $table->string('provider_subscription_id', 150)
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'store_id',
                'status',
                'ends_at',
            ]);

            $table->index('provider_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_subscriptions');
    }
};
