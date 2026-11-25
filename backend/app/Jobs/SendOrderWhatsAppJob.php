<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderWhatsAppJob implements ShouldQueue
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
                'store.owner',
                'items',
            ])
            ->find($this->orderId);

        if (! $order) {
            return;
        }

        $seller = $order->store?->owner;

        if (! $seller || ! $seller->phone) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | V1:
        | Generate WhatsApp deep-link payload.
        |
        | Future:
        | WhatsApp Business API / provider.
        |--------------------------------------------------------------------------
        */
    }
}