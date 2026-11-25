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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('store_name', 150);

            $table->string('slug', 180)
                ->unique();

            $table->string('house_number', 100);

            $table->boolean('is_open')
                ->default(true);

            $table->boolean('is_active')
                ->default(true);

            $table->string('bonus_text', 255)
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->string('logo')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'is_active',
            ]);

            $table->index([
                'is_open',
                'is_active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
