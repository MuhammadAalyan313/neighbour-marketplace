<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with([
                'store',
                'items',
                'buyer',
            ])
            ->find($this->orderId);

        if (! $order) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Email notification will be implemented later.
        |--------------------------------------------------------------------------
        */
    }
}