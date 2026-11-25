<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderStatusService
{
    public function __construct(
        private readonly OrderNotificationService $notifications
    ) {}




 

public function update(
    Order $order,
    User $seller,
    OrderStatus $newStatus,
    ?string $cancellationReason = null,
    bool $paymentReceived = false,
    ?string $paymentReference = null,
    ?OrderStatus $expectedCurrentStatus = null
): Order {
    $result = DB::transaction(function () use (
        $order,
        $seller,
        $newStatus,
        $cancellationReason,
        $paymentReceived,
        $paymentReference,
        $expectedCurrentStatus
    ) {
        $lockedOrder = Order::query()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $lockedOrder->load('store');

        abort_unless(
            $lockedOrder->store
                && (int) $lockedOrder->store->user_id
                    === (int) $seller->id,
            403,
            'You cannot update this order.'
        );

        $currentStatus = $lockedOrder->status;

        // Protect scheduled expiry from changing an accepted order.
        if (
            $expectedCurrentStatus !== null
            && $currentStatus !== $expectedCurrentStatus
        ) {
            abort(
                409,
                'Order status has changed. Please refresh and try again.'
            );
        }

        // Direct seller payments cannot be refunded automatically.
        if (
            $newStatus === OrderStatus::CANCELLED
            && $lockedOrder->payment_status === PaymentStatus::PAID
        ) {
            abort(
                409,
                'This order has already been paid. Resolve the refund with the buyer before cancellation.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Idempotent Status Update
        |--------------------------------------------------------------------------
        */

        if ($currentStatus === $newStatus) {
            if (
                $paymentReceived
                && $newStatus === OrderStatus::DELIVERED
                && $lockedOrder->payment_status !== PaymentStatus::PAID
            ) {
                $this->confirmPaymentOnDelivery(
                    $lockedOrder,
                    $seller,
                    $paymentReference
                );

                $lockedOrder->checkoutBatch?->syncPaymentStatus();

                $lockedOrder->load([
                    'store.owner',
                    'buyer',
                    'items',
                ]);

                $this->notifications->paymentConfirmed(
                    $lockedOrder
                );
            }

            return [
                'order_id' => $lockedOrder->id,
                'changed' => false,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Transition
        |--------------------------------------------------------------------------
        */

        $allowedTransitions = [
            OrderStatus::PENDING->value => [
                OrderStatus::ACCEPTED,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::ACCEPTED->value => [
                OrderStatus::DELIVERED,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::DELIVERED->value => [],
            OrderStatus::CANCELLED->value => [],
        ];

        $allowed = $allowedTransitions[
            $currentStatus->value
        ] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Cannot change order from "
                    . "{$currentStatus->value} to "
                    . "{$newStatus->value}.",
                ],
            ]);
        }

        $now = now();

        $items = $lockedOrder->items()
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Cancellation + Inventory Restoration
        |--------------------------------------------------------------------------
        */

        if ($newStatus === OrderStatus::CANCELLED) {
            $products = Product::query()
                ->whereIn(
                    'id',
                    $items->pluck('product_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                if (! $item->stock_deducted) {
                    continue;
                }

                $product = $products->get($item->product_id);

                if (
                    $product
                    && $product->stock_quantity !== null
                ) {
                    $product->increment(
                        'stock_quantity',
                        (int) $item->quantity
                    );
                }

                $item->stock_deducted = false;
                $item->save();
            }

            $lockedOrder->cancelled_at = $now;
            $lockedOrder->cancellation_reason = $cancellationReason;
        }

        /*
        |--------------------------------------------------------------------------
        | Acceptance
        |--------------------------------------------------------------------------
        */

        if ($newStatus === OrderStatus::ACCEPTED) {
            $lockedOrder->accepted_at = $now;
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery + Sales Count
        |--------------------------------------------------------------------------
        */

        if ($newStatus === OrderStatus::DELIVERED) {
            $products = Product::query()
                ->whereIn(
                    'id',
                    $items->pluck('product_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if ($product) {
                    $product->increment(
                        'sales_count',
                        (int) $item->quantity
                    );
                }
            }

            $lockedOrder->delivered_at = $now;
        }

        /*
        |--------------------------------------------------------------------------
        | Save Status + Optional Payment
        |--------------------------------------------------------------------------
        */

        $lockedOrder->status = $newStatus;

        $paymentConfirmedNow = false;

        if (
            $newStatus === OrderStatus::DELIVERED
            && $paymentReceived
            && $lockedOrder->payment_status !== PaymentStatus::PAID
        ) {
            $this->confirmPaymentOnDelivery(
                $lockedOrder,
                $seller,
                $paymentReference
            );

            $paymentConfirmedNow = true;
        }

        $lockedOrder->save();

        if ($paymentConfirmedNow) {
            $lockedOrder->checkoutBatch?->syncPaymentStatus();
        }

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        $lockedOrder->load([
            'store.owner',
            'buyer',
            'items',
        ]);

        $this->notifications->statusUpdated($lockedOrder);

        if ($paymentConfirmedNow) {
            $this->notifications->paymentConfirmed(
                $lockedOrder
            );
        }

        return [
            'order_id' => $lockedOrder->id,
            'changed' => true,
        ];
    }, 3);

    $updatedOrder = Order::query()
        ->with([
            'store',
            'buyer',
            'items',
        ])
        ->findOrFail($result['order_id']);

    if ($result['changed']) {
        try {
            OrderStatusUpdated::dispatch($updatedOrder);
        } catch (Throwable $exception) {
            Log::warning(
                'Order status broadcast failed.',
                [
                    'order_id' => $updatedOrder->id,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }

    return $updatedOrder;
}





private function confirmPaymentOnDelivery(
    Order $order,
    User $seller,
    ?string $reference
): void {
    $method = strtolower(
        trim((string) $order->payment_method)
    );

    abort_unless(
        in_array($method, [
            'cash',
            'cod',
            'cash_on_delivery',
            'cash_on_pickup',
            'easypaisa',
            'jazzcash',
            'bank_transfer',
        ], true),
        422,
        'Unsupported manual payment method.'
    );

    $now = now();

    $metadata = $order->metadata ?? [];

    $metadata['payment_verification'] = [
        'verification_type' => 'seller_manual',
        'verified_by' => $seller->id,
        'verified_at' => $now->toISOString(),
        'payment_method' => $method,
    ];

    $order->payment_status =
        \App\Enums\PaymentStatus::PAID;

    $order->paid_at = $now;

    if ($reference !== null && trim($reference) !== '') {
        $order->payment_reference = trim($reference);
    }

    $order->metadata = $metadata;

    $order->save();
}

}
