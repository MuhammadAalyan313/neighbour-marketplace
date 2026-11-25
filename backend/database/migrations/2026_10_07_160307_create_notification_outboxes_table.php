<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_outboxes', function (Blueprint $table) {

            $table->id();

            $table->string('type', 100);

            $table->string('channel', 30);

            $table->nullableMorphs('notifiable');

            $table->nullableMorphs('related');

            $table->json('payload');

            $table->string('status', 30)
                ->default('pending');

            $table->unsignedSmallInteger('attempts')
                ->default(0);

            $table->timestamp('available_at')
                ->nullable();

            $table->timestamp('processed_at')
                ->nullable();

            $table->text('last_error')
                ->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'available_at',
            ]);

            $table->index([
                'type',
                'channel',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notification_outboxes'
        );
    }
};