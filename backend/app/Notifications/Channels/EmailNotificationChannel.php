<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailNotificationChannel implements NotificationChannel
{
    public function send(NotificationOutbox $notification): void
    {
        $payload = $notification->payload ?? [];

        $recipient = $payload['email']
            ?? $notification->notifiable?->email;

        if (
            ! is_string($recipient) ||
            ! filter_var($recipient, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('Valid notification email recipient not found.');
        }

        $subject = $payload['subject'] ?? 'Marketplace Notification';
        $body = $payload['body'] ?? 'You have a new marketplace notification.';
        $receipt = $payload['receipt'] ?? null;

        if (! is_array($receipt) || empty($receipt['order_number'])) {
            Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            });

            return;
        }

        Mail::send(
            [
                'html' => 'emails.order-notification',
                'text' => 'emails.order-notification-text',
            ],
            [
                'receipt' => $receipt,
                'notificationType' => (string) $notification->type,
                'body' => $body,
            ],
            function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            }
        );
    }
}
