<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\StoreSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireStoreSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description =
        'Expire store subscriptions whose validity has ended.';

    public function handle(): int
    {
        try {
            $count = 0;

            StoreSubscription::query()
                ->whereIn('status', [
                    SubscriptionStatus::TRIAL->value,
                    SubscriptionStatus::ACTIVE->value,
                ])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->chunkById(200, function ($subscriptions) use (&$count) {
                    $ids = $subscriptions->pluck('id')->all();

                    $count += StoreSubscription::query()
                        ->whereIn('id', $ids)
                        ->whereIn('status', [
                            SubscriptionStatus::TRIAL->value,
                            SubscriptionStatus::ACTIVE->value,
                        ])
                        ->where('ends_at', '<=', now())
                        ->update([
                            'status' => SubscriptionStatus::EXPIRED->value,
                            'updated_at' => now(),
                        ]);
                });

            $this->info(
                "{$count} store subscriptions expired."
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Subscription expiration failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $this->error(
                'Subscription expiration failed. Check application logs.'
            );

            return self::FAILURE;
        }
    }
}
