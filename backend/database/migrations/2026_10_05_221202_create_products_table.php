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
         Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('title', 180);

            $table->text('description')
                ->nullable();

            $table->decimal('price', 12, 2);

            $table->string('image')
                ->nullable();

            $table->string('badge', 100)
                ->nullable();

            $table->boolean('is_available')
                ->default(true);

            $table->unsignedInteger('sales_count')
                ->default(0);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'store_id',
                'is_available',
            ]);

            $table->index([
                'category_id',
                'is_available',
            ]);

            $table->index([
                'store_id',
                'category_id',
                'is_available',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
