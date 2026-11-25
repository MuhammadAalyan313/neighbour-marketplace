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
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();

            $table->enum('channel', [
                'email',
                'phone',
            ]);

            $table->string('identifier', 255);

            $table->string('purpose', 50);

            /*
             * Store hashed OTP, not plain OTP.
             */
            $table->string('code');

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')
                ->nullable();

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->timestamps();

            $table->index([
                'channel',
                'identifier',
                'purpose',
            ]);

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
