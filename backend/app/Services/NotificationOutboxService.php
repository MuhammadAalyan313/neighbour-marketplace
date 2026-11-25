<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationOutboxService
{
    public function create(
        string $type,
        NotificationChannel|string $channel,
        Model $notifiable,
        ?Model $related,
        array $payload,
        ?string $deduplicationKey = null
    ): NotificationOutbox {
        $channel = $channel instanceof NotificationChannel
            ? $channel
            : NotificationChannel::from($channel);

        $attributes = [
            'type' => $type,
            'channel' => $channel->value,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'payload' => $payload,
            'deduplication_key' => $deduplicationKey,
            'status' => NotificationStatus::PENDING->value,
            'available_at' => now(),
        ];

        if ($deduplicationKey === null) {
            return NotificationOutbox::query()->create(
                $attributes
            );
        }

        // Atomic insert: prevents duplicate notification rows
        // when the same event is handled concurrently.
        DB::table('notification_outboxes')->insertOrIgnore([
            'type' => $type,
            'channel' => $channel->value,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'payload' => json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            ),
            'deduplication_key' => $deduplicationKey,
            'status' => NotificationStatus::PENDING->value,
            'attempts' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return NotificationOutbox::query()
            ->where('deduplication_key', $deduplicationKey)
            ->firstOrFail();
    }
}
