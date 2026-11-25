<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationOutbox extends Model
{
    protected $fillable = [
        'type',
        'channel',
        'notifiable_type',
        'notifiable_id',
        'related_type',
        'related_id',
        'payload',
        'deduplication_key',
        'status',
        'attempts',
        'available_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => NotificationStatus::class,
            'channel' => NotificationChannel::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}