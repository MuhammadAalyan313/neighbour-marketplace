<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotificationOutboxJob;
use App\Models\NotificationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchPendingNotifications extends Command
{
    protected $signature = 'notifications:dispatch-pending';

    protected $description =
        'Dispatch due pending notification outbox records.';

    public function handle(): int
    {
        $lock = Cache::lock(
            'notification-outbox-dispatch-lock',
            55
        );

        if (! $lock->get()) {
            $this->info(
                'Notification dispatcher is already running.'
            );

            return self::SUCCESS;
        }

        try {
            NotificationOutbox::query()
    ->where('status', NotificationStatus::PROCESSING->value)
    ->where('updated_at', '<', now()->subMinutes(10))
    ->where('attempts', '<', 5)
    ->update([
        'status' => NotificationStatus::PENDING,
        'available_at' => now(),
    ]);

NotificationOutbox::query()
    ->where('status', NotificationStatus::PROCESSING->value)
    ->where('updated_at', '<', now()->subMinutes(10))
    ->where('attempts', '>=', 5)
    ->update([
        'status' => NotificationStatus::FAILED,
        'available_at' => null,
        'last_error' => 'Processing timed out after maximum attempts.',
    ]);
            $notifications = NotificationOutbox::query()
                ->where(
                    'status',
                    NotificationStatus::PENDING->value
                )
                ->where(function ($query) {
                    $query->whereNull('available_at')
                        ->orWhere(
                            'available_at',
                            '<=',
                            now()
                        );
                })
                ->orderBy('id')
                ->limit(100)
                ->pluck('id');

            foreach ($notifications as $id) {
                ProcessNotificationOutboxJob::dispatch(
                    (int) $id
                );
            }

            $this->info(
                "Dispatched {$notifications->count()} notifications."
            );

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
