<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerPaymentService
{
    
public function __construct(
    private readonly OrderNotificationService $notifications
) {}


 
public function confirm(
    Order $order,
    User $seller,
    ?string $reference = null
): Order {
    return DB::transaction(function () use (
        $order,
        $seller,
        $reference
    ) {
        $lockedOrder = Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();

        $ownsOrder = $seller->stores()
            ->whereKey($lockedOrder->store_id)
            ->where('is_active', true)
            ->exists();

        abort_unless(
            $ownsOrder,
            403,
            'You are not authorized to confirm this payment.'
        );

        // Already confirmed: do not generate duplicate notifications.
        if ($lockedOrder->payment_status === PaymentStatus::PAID) {
            return $lockedOrder->load(['store', 'items']);
        }

        if ($lockedOrder->status === OrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'order' => [
                    'Cancelled orders cannot be marked as paid.',
                ],
            ]);
        }

        $method = strtolower(
            trim((string) $lockedOrder->payment_method)
        );

        $supportedMethods = [
            'cash',
            'cod',
            'cash_on_delivery',
            'cash_on_pickup',
            'bank_transfer',
            'easypaisa',
            'jazzcash',
        ];

        if (! in_array($method, $supportedMethods, true)) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Unsupported manual payment method.',
                ],
            ]);
        }

        $cashMethods = [
            'cash',
            'cod',
            'cash_on_delivery',
            'cash_on_pickup',
        ];

        if (
            in_array($method, $cashMethods, true)
            && ! in_array(
                $lockedOrder->status,
                [
                    OrderStatus::ACCEPTED,
                    OrderStatus::DELIVERED,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Accept the order before confirming cash payment.',
                ],
            ]);
        }

        $now = now();
        $metadata = $lockedOrder->metadata ?? [];

        $metadata['payment_verification'] = [
            'verification_type' => 'seller_manual',
            'verified_by' => $seller->id,
            'verified_at' => $now->toISOString(),
            'payment_method' => $method,
        ];

        $lockedOrder->update([
            'payment_status' => PaymentStatus::PAID,
            'payment_reference' => $reference !== null
                ? (trim($reference) ?: null)
                : null,
            'paid_at' => $now,
            'metadata' => $metadata,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Synchronize Parent Checkout Batch
        |--------------------------------------------------------------------------
        */

        $lockedOrder->checkoutBatch?->syncPaymentStatus();

        /*
        |--------------------------------------------------------------------------
        | Queue Buyer Notification
        |--------------------------------------------------------------------------
        */

        $lockedOrder->load([
            'store.owner',
            'buyer',
            'items',
        ]);

        $this->notifications->paymentConfirmed($lockedOrder);

        return $lockedOrder->fresh([
            'store',
            'items',
        ]);
    }, 3);
}

}
