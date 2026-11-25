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
         Schema::create('checkout_batches', function (Blueprint $table) {
            $table->id();

            $table->string('batch_number', 70)
                ->unique();

            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Guest or registered buyer snapshot.
             */
            $table->string('buyer_name', 150);

            $table->string('buyer_email', 255)
                ->nullable();

            $table->string('buyer_phone', 30);

            $table->string('buyer_house_number', 100);

            $table->decimal('grand_total', 12, 2)
                ->default(0);

            $table->unsignedInteger('store_count')
                ->default(0);

            $table->unsignedInteger('order_count')
                ->default(0);

            $table->enum('status', [
                'pending',
                'completed',
                'cancelled',
                'failed',
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

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'buyer_id',
                'created_at',
            ]);

            $table->index([
                'status',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_batches');
    }
};
