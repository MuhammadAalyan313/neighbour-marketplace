<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\NotificationOutbox;
use App\Services\SellerOrderNotificationService;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationChannel implements NotificationChannel
{
    public function __construct(
        protected SellerOrderNotificationService $notificationService
    ) {
    }

  
public function send(
    NotificationOutbox $notification
): void {
    $order = $notification->related;

    if (! $order instanceof \App\Models\Order) {
        throw new \RuntimeException(
            'WhatsApp notification order not found.'
        );
    }

    $url = $this->notificationService
        ->whatsappUrl($order);

    \Illuminate\Support\Facades\Log::info(
        'WhatsApp notification link prepared.',
        [
            'notification_id' => $notification->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'link_generated' => $url !== '',
        ]
    );
}

}