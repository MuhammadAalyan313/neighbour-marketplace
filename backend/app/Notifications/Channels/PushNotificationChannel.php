<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\NotificationOutbox;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class PushNotificationChannel implements NotificationChannel
{
    public function send(NotificationOutbox $notification): void
    {
        $recipient = $notification->notifiable;

        if (! $recipient instanceof User) {
            throw new RuntimeException(
                'Push notification recipient must be a user.'
            );
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $recipient->getKey())
            ->get();

        if ($subscriptions->isEmpty()) {
            Log::info('Push skipped: no device subscriptions.', [
                'notification_id' => $notification->id,
                'user_id' => $recipient->getKey(),
            ]);

            return;
        }

        $subject = config('services.webpush.subject');
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $subject || ! $publicKey || ! $privateKey) {
            throw new RuntimeException(
                'Web Push VAPID configuration is missing.'
            );
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = $notification->payload ?? [];

        $message = json_encode([
            'title' => $payload['title']
                ?? 'Marketplace Notification',

            'body' => $payload['body']
                ?? 'You have a new notification.',

            'url' => $payload['url'] ?? '/',

            'tag' => 'notification-' . $notification->id,
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                    'contentEncoding' => 'aes128gcm',
                ]),
                $message
            );
        }

        $failed = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = (string) $report->getEndpoint();

            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('user_id', $recipient->getKey())
                    ->where(
                        'endpoint_hash',
                        hash('sha256', $endpoint)
                    )
                    ->delete();

                continue;
            }

            $reason = (string) $report->getReason();

            Log::warning('Web Push delivery failed.', [
                'notification_id' => $notification->id,
                'endpoint_hash' => hash('sha256', $endpoint),
                'reason' => $reason,
            ]);

            $failed[] = $reason;
        }

        if ($failed !== []) {
            throw new RuntimeException(
                'Web Push failed for '
                . count($failed)
                . ' device(s).'
            );
        }
    }
}
