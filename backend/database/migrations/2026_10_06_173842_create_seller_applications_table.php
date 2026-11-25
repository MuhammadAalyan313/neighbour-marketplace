<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_applications', function (Blueprint $table) {
            $table->id();

            // Applicant
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            // Proposed store information
            $table->string('store_name', 150);
            $table->string('store_slug', 180);
            $table->string('house_number', 100);
            $table->text('description')->nullable();
            $table->string('bonus_text', 255)->nullable();

            // Application workflow
            $table->string('status', 30)->default('pending')->index();

            // Admin review
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Future extensibility
            $table->json('metadata')->nullable();

            $table->timestamps();

            // One user should not have multiple pending applications
            $table->index(['user_id', 'status']);

            // Store slug should eventually be unique
            $table->unique('store_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_applications');
    }
};