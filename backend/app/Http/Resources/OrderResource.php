<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status?->value;
        $paymentStatus = $this->payment_status?->value;

        $isCancelled = $status === OrderStatus::CANCELLED->value;
        $isPaid = $paymentStatus === PaymentStatus::PAID->value;

        $paymentMethod = $this->payment_method;

        $manualMethods = [
            'cash',
            'cod',
            'cash_on_delivery',
            'cash_on_pickup',
            'easypaisa',
            'jazzcash',
            'bank_transfer',
        ];

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'checkout_batch_id' => $this->checkout_batch_id,
            'store_id' => $this->store_id,

            'buyer_name' => $this->buyer_name,
            'buyer_house_number' => $this->buyer_house_number,

            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'delivery_fee' => (string) $this->delivery_fee,
            'platform_fee' => (string) $this->platform_fee,
            'total_amount' => (string) $this->total_amount,

            'fulfillment_type' => $this->fulfillment_type?->value,
            'status' => $status,

            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'paid_at' => $this->paid_at?->toISOString(),

            'payment' => [
                'method' => $paymentMethod,
                'status' => $paymentStatus,
                'is_paid' => $isPaid,
                'requires_manual_confirmation' =>
                    ! $isPaid
                    && ! $isCancelled
                    && in_array(
                        $paymentMethod,
                        $manualMethods,
                        true
                    ),
                'paid_at' => $this->paid_at?->toISOString(),
            ],

            'store' => new StoreResource(
                $this->whenLoaded('store')
            ),

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'timeline' => [
                [
                    'status' => OrderStatus::PENDING->value,
                    'label' => 'Order placed',
                    'completed' => true,
                    'active' => $status === OrderStatus::PENDING->value,
                    'at' => $this->created_at?->toISOString(),
                ],
                [
                    'status' => OrderStatus::ACCEPTED->value,
                    'label' => 'Order accepted',
                    'completed' =>
                        ! $isCancelled
                        && $this->accepted_at !== null,
                    'active' =>
                        ! $isCancelled
                        && $status === OrderStatus::ACCEPTED->value,
                    'at' => $this->accepted_at?->toISOString(),
                ],
                [
                    'status' => OrderStatus::DELIVERED->value,
                    'label' => 'Order delivered',
                    'completed' =>
                        ! $isCancelled
                        && $this->delivered_at !== null,
                    'active' =>
                        ! $isCancelled
                        && $status === OrderStatus::DELIVERED->value,
                    'at' => $this->delivered_at?->toISOString(),
                ],
                [
                    'status' => OrderStatus::CANCELLED->value,
                    'label' => 'Order cancelled',
                    'completed' => $this->cancelled_at !== null,
                    'active' => $isCancelled,
                    'at' => $this->cancelled_at?->toISOString(),
                ],
            ],

            'created_at' => $this->created_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}
