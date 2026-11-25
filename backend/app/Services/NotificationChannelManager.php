<?php

namespace App\Services;

use App\Enums\NotificationChannel as NotificationChannelEnum;
use App\Models\NotificationOutbox;
use App\Notifications\Channels\EmailNotificationChannel;
use App\Notifications\Channels\PushNotificationChannel;
use App\Notifications\Channels\WhatsAppNotificationChannel;
use RuntimeException;

class NotificationChannelManager
{
    public function __construct(
        protected WhatsAppNotificationChannel $whatsapp,
        protected PushNotificationChannel $push,
        protected EmailNotificationChannel $email,
    ) {
    }

    public function send(
        NotificationOutbox $notification
    ): void {
        $channel = $notification->channel;

        if (! $channel instanceof NotificationChannelEnum) {
            $channel = NotificationChannelEnum::from($channel);
        }

        match ($channel) {
            NotificationChannelEnum::WHATSAPP =>
                $this->whatsapp->send($notification),

            NotificationChannelEnum::PUSH =>
                $this->push->send($notification),

            NotificationChannelEnum::EMAIL =>
                $this->email->send($notification),

            default => throw new RuntimeException(
                "Unsupported notification channel: {$channel->value}"
            ),
        };
    }
}