<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {

            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->tinyInteger('rating');

            $table->text('comment')->nullable();

            $table->boolean('is_published')
                ->default(true);

            $table->timestamps();

            $table->index(['store_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};