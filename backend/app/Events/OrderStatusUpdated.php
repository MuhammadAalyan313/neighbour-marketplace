<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->order->buyer_id) {
            $channels[] = new PrivateChannel(
                'buyer.' . $this->order->buyer_id
            );
        }

        $channels[] = new PrivateChannel(
            'seller.' . $this->order->store->user_id
        );

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'store_id' => $this->order->store_id,
                'status' => $this->order->status->value,
                'payment_status' => $this->order->payment_status->value,
                'updated_at' => $this->order->updated_at?->toISOString(),
            ],
        ];
    }
}