<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->index(
                ['store_id', 'status', 'ends_at'],
                'store_subscriptions_active_lookup_index'
            );

            $table->index(
                ['subscription_plan_id', 'status'],
                'store_subscriptions_plan_status_index'
            );
        });

        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->index(
                ['store_subscription_id', 'status'],
                'subscription_transactions_status_index'
            );

            $table->index(
                ['provider', 'provider_transaction_id'],
                'subscription_transactions_provider_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('store_subscriptions', function (Blueprint $table) {
            $table->dropIndex(
                'store_subscriptions_active_lookup_index'
            );

            $table->dropIndex(
                'store_subscriptions_plan_status_index'
            );
        });

        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->dropIndex(
                'subscription_transactions_status_index'
            );

            $table->dropIndex(
                'subscription_transactions_provider_index'
            );
        });
    }
};