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
       Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);

            $table->string('email', 255)
                ->unique();

            $table->timestamp('email_verified_at')
                ->nullable();

            $table->string('phone', 30)
                ->unique();

            $table->timestamp('phone_verified_at')
                ->nullable();

            /*
             * Nullable because future passwordless
             * OTP authentication may be supported.
             *
             * Password-based accounts will always
             * receive a hashed password.
             */
            $table->string('password')
                ->nullable();

            $table->string('house_number', 100)
                ->nullable();

            $table->enum('role', [
                'admin',
                'seller',
                'buyer',
            ])->default('buyer');

            $table->boolean('is_active')
                ->default(true);

            $table->rememberToken();

            $table->timestamps();

            $table->index([
                'role',
                'is_active',
            ]);
        });

      Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 255)->primary();

            $table->string('token');

            $table->timestamp('created_at')
                ->nullable();
        });

    Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->longText('payload');

            $table->integer('last_activity')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
