<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CheckoutBatchStatus;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;

class CheckoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'buyer_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_house_number',
        'grand_total',
        'store_count',
        'order_count',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'paid_at',
        'metadata',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'grand_total' => 'decimal:2',
            'store_count' => 'integer',
            'order_count' => 'integer',

            'status' => CheckoutBatchStatus::class,
            'payment_status' => PaymentStatus::class,
            
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === CheckoutBatchStatus::COMPLETED;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

public function calculateOverallStatus(): string
{
    $orders = $this->orders;

    if ($orders->isEmpty()) {
        return 'pending';
    }

    if ($orders->every(
        fn ($order) => $order->status === OrderStatus::CANCELLED
    )) {
        return 'cancelled';
    }

    if ($orders->every(
        fn ($order) => $order->status === OrderStatus::DELIVERED
    )) {
        return 'delivered';
    }

    if ($orders->every(
        fn ($order) =>
            in_array(
                $order->status,
                [
                    OrderStatus::DELIVERED,
                    OrderStatus::CANCELLED,
                ],
                true
            )
    )) {
        return 'completed';
    }

    if ($orders->contains(
        fn ($order) => $order->status === OrderStatus::ACCEPTED
    )) {
        return 'processing';
    }

    return 'pending';
}


public function syncPaymentStatus(): void
{
    // Call inside the payment transaction.
    // Lock the batch to serialize concurrent payment updates.
    $batch = self::query()
        ->whereKey($this->getKey())
        ->lockForUpdate()
        ->firstOrFail();

    $orders = $batch->orders()
        ->select([
            'id',
            'payment_status',
            'paid_at',
        ])
        ->get();

    if ($orders->isEmpty()) {
        return;
    }

    $statuses = $orders->pluck('payment_status');

    if ($statuses->every(
        fn ($status) => $status === PaymentStatus::PAID
    )) {
        $status = PaymentStatus::PAID;
    } elseif ($statuses->every(
        fn ($status) => $status === PaymentStatus::UNPAID
    )) {
        $status = PaymentStatus::UNPAID;
    } elseif ($statuses->every(
        fn ($status) => $status === PaymentStatus::REFUNDED
    )) {
        $status = PaymentStatus::REFUNDED;
    } elseif ($statuses->every(
        fn ($status) => $status === PaymentStatus::FAILED
    )) {
        $status = PaymentStatus::FAILED;
    } else {
        $status = PaymentStatus::PENDING;
    }

    $batch->payment_status = $status;

    $batch->paid_at = $status === PaymentStatus::PAID
        ? $orders->max('paid_at')
        : null;

    $batch->save();

    $this->setRawAttributes(
        $batch->getAttributes(),
        true
    );
}


}