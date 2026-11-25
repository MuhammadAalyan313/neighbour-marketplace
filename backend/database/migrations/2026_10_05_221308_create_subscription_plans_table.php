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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);

            $table->string('slug', 120)
                ->unique();

            $table->text('description')
                ->nullable();

            $table->decimal('price', 12, 2);

            $table->char('currency', 3)
                ->default('PKR');

            $table->enum('billing_interval', [
                'monthly',
                'yearly',
            ])->default('monthly');

            /*
             * Current:
             * none + 0
             *
             * Future:
             * percentage / fixed
             */
            $table->enum('commission_type', [
                'none',
                'percentage',
                'fixed',
            ])->default('none');

            $table->decimal('commission_rate', 8, 4)
                ->default(0);

            $table->boolean('is_active')
                ->default(true);

            /*
             * Feature flags/limits can evolve
             * without constantly altering schema.
             */
            $table->json('features')
                ->nullable();

            $table->timestamps();

            $table->index([
                'is_active',
                'billing_interval',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
