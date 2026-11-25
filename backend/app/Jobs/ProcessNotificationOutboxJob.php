<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationOutbox;
use App\Services\NotificationChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessNotificationOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(
        public int $notificationId
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(
        NotificationChannelManager $channelManager
    ): void {
        $notification = $this->claimNotification();

        if (! $notification) {
            return;
        }

        try {
            $notification->loadMissing([
                'notifiable',
                'related',
            ]);

            $channelManager->send($notification);

            $this->markProcessed($notification->id);
        } catch (Throwable $exception) {
            $this->markFailed(
                $notification->id,
                $exception
            );

            // Retry is scheduled through the outbox,
            // not by immediately retrying the same job.
        }
    }

    private function claimNotification(): ?NotificationOutbox
    {
        return DB::transaction(function () {
            $notification = NotificationOutbox::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->first();

            if (! $notification) {
                return null;
            }

            // Only pending records can be claimed.
            if (
                $notification->status !==
                NotificationStatus::PENDING
            ) {
                return null;
            }

            if (
                $notification->available_at?->isFuture()
            ) {
                return null;
            }
            if ($notification->attempts >= 5) {
    $notification->update([
        'status' => NotificationStatus::FAILED,
        'available_at' => null,
    ]);

    return null;
}

            $notification->update([
                'status' => NotificationStatus::PROCESSING,
                'attempts' => $notification->attempts + 1,
                'last_error' => null,
            ]);

            return $notification->fresh();
        });
    }

    private function markProcessed(int $notificationId): void
    {
        NotificationOutbox::query()
            ->whereKey($notificationId)
            ->where(
                'status',
                NotificationStatus::PROCESSING->value
            )
            ->update([
                'status' => NotificationStatus::PROCESSED,
                'processed_at' => now(),
                'available_at' => null,
                'last_error' => null,
            ]);
    }

    private function markFailed(
        int $notificationId,
        Throwable $exception
    ): void {
        DB::transaction(function () use (
            $notificationId,
            $exception
        ) {
            $notification = NotificationOutbox::query()
                ->whereKey($notificationId)
                ->lockForUpdate()
                ->first();

            if (! $notification) {
                return;
            }

            if (
                $notification->status !==
                NotificationStatus::PROCESSING
            ) {
                return;
            }

            $isFinalAttempt =
                $notification->attempts >= $this->tries;

            $delaySeconds = min(
                3600,
                30 * (2 ** max(
                    0,
                    $notification->attempts - 1
                ))
            );

            $notification->update([
                'status' => $isFinalAttempt
                    ? NotificationStatus::FAILED
                    : NotificationStatus::PENDING,

                'available_at' => $isFinalAttempt
                    ? null
                    : now()->addSeconds($delaySeconds),

                'last_error' => mb_substr(
                    $exception->getMessage(),
                    0,
                    65535
                ),
            ]);

            Log::error(
                'Notification outbox processing failed.',
                [
                    'notification_id' => $notification->id,
                    'attempts' => $notification->attempts,
                    'final_attempt' => $isFinalAttempt,
                    'error' => $exception->getMessage(),
                ]
            );
        });
    }
}
