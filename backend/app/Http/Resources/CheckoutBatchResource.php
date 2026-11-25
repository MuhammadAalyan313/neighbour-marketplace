<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\OrderStatus;

class CheckoutBatchResource extends JsonResource
{
  
public function toArray(Request $request): array
{
    /*
    |--------------------------------------------------------------------------
    | Load Batch Orders
    |--------------------------------------------------------------------------
    */

    $this->resource->loadMissing('orders');

    $orders = $this->resource->orders;

    /*
    |--------------------------------------------------------------------------
    | Calculate Payment Summary
    |--------------------------------------------------------------------------
    */

    $paymentSummary = [
        'total' => $orders->count(),
        'unpaid' => 0,
        'pending' => 0,
        'paid' => 0,
        'failed' => 0,
        'refunded' => 0,
    ];

    foreach ($orders as $order) {
        $status = $order->payment_status?->value;

        if (isset($paymentSummary[$status])) {
            $paymentSummary[$status]++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Overall Payment Status
    |--------------------------------------------------------------------------
    |
    | All paid       => paid
    | All unpaid     => unpaid
    | All refunded   => refunded
    | Mixed statuses => pending
    |
    */

    $overallPaymentStatus = $this->payment_status?->value;

    if ($orders->isNotEmpty()) {
        if (
            $paymentSummary['paid'] === $orders->count()
        ) {
            $overallPaymentStatus =
                \App\Enums\PaymentStatus::PAID->value;

        } elseif (
            $paymentSummary['refunded'] === $orders->count()
        ) {
            $overallPaymentStatus =
                \App\Enums\PaymentStatus::REFUNDED->value;

        } elseif (
            $paymentSummary['unpaid'] === $orders->count()
        ) {
            $overallPaymentStatus =
                \App\Enums\PaymentStatus::UNPAID->value;

        } elseif (
            $paymentSummary['failed'] === $orders->count()
        ) {
            $overallPaymentStatus =
                \App\Enums\PaymentStatus::FAILED->value;

        } else {
            $overallPaymentStatus =
                \App\Enums\PaymentStatus::PENDING->value;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Batch Paid Timestamp
    |--------------------------------------------------------------------------
    */

    $paidAt = null;

    if (
        $orders->isNotEmpty()
        && $paymentSummary['paid'] === $orders->count()
    ) {
        $paidAt = $orders
            ->max('paid_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Order Status Summary
    |--------------------------------------------------------------------------
    */

    $statusSummary = [
        'pending' => $orders->filter(
            fn ($order) =>
                $order->status === OrderStatus::PENDING
        )->count(),

        'accepted' => $orders->filter(
            fn ($order) =>
                $order->status === OrderStatus::ACCEPTED
        )->count(),

        'delivered' => $orders->filter(
            fn ($order) =>
                $order->status === OrderStatus::DELIVERED
        )->count(),

        'cancelled' => $orders->filter(
            fn ($order) =>
                $order->status === OrderStatus::CANCELLED
        )->count(),
    ];

    /*
    |--------------------------------------------------------------------------
    | Final Response
    |--------------------------------------------------------------------------
    */

    return [
        'id' => $this->id,

        'batch_number' => $this->batch_number,

        'buyer_id' => $this->buyer_id,

        'buyer_name' => $this->buyer_name,

        'buyer_email' => $this->buyer_email,

        'buyer_phone' => $this->buyer_phone,

        'buyer_house_number' => $this->buyer_house_number,

        'grand_total' => (string) $this->grand_total,

        'store_count' => (int) $this->store_count,

        'order_count' => (int) $this->order_count,

        'status' => $this->status?->value,

        'overall_status' => $this->calculateOverallStatus(),

        'payment_status' => $overallPaymentStatus,

        'payment_method' => $this->payment_method,

        'paid_at' => $paidAt?->toISOString(),

        'summary' => $statusSummary,

        'payment_summary' => $paymentSummary,

        'orders' => OrderResource::collection($orders),

        'created_at' => $this->created_at?->toISOString(),

        'updated_at' => $this->updated_at?->toISOString(),
    ];
}

}