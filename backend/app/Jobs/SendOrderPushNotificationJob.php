<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with('store.owner')
            ->find($this->orderId);

        if (! $order) {
            return;
        }

        $seller = $order->store?->owner;

        if (! $seller) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Push notification implementation later.
        |--------------------------------------------------------------------------
        */
    }
}