<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The order that was created.
     */
    public function __construct(
        public Order $order
    ) {
    }

    /**
     * Broadcast the event on the seller's private channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'seller.' . $this->order->store->user_id
            ),
        ];
    }

    /**
     * Event name on the frontend.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * Data sent to the frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,

                'order_number' =>
                    $this->order->order_number,

                'store_id' =>
                    $this->order->store_id,

                'status' =>
                    $this->order->status->value,

                'payment_status' =>
                    $this->order->payment_status->value,

                'total_amount' =>
                    $this->order->total_amount,

                'buyer_name' =>
                    $this->order->buyer_name,

                'buyer_house_number' =>
                    $this->order->buyer_house_number,

                'created_at' =>
                    $this->order->created_at?->toISOString(),
            ],
        ];
    }
}