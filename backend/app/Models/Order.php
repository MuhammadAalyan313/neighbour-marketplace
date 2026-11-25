<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\NotificationOutbox;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\FulfillmentType;


class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_batch_id',
        'order_number',
        'access_token',
        'buyer_id',
        'store_id',

        'buyer_name',
        'buyer_email',
        'buyer_house_number',
        'buyer_phone',

        'subtotal',
        'discount_amount',
        'delivery_fee',
        'platform_fee',
        'platform_fee_rate',
        'total_amount',

        'fulfillment_type',

        'status',
        'payment_status',
        'payment_method',
        'payment_reference',

        'paid_at',
        'accepted_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',

        'metadata',
    ];

    protected $hidden = [
    'access_token',
    'payment_reference',
];
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'platform_fee_rate' => 'decimal:4',
            'total_amount' => 'decimal:2',

            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_type' => FulfillmentType::class,

            'paid_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    public function checkoutBatch(): BelongsTo
    {
        return $this->belongsTo(CheckoutBatch::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
 
    public function isPending(): bool
{
    return $this->status === OrderStatus::PENDING;
}

public function isAccepted(): bool
{
    return $this->status === OrderStatus::ACCEPTED;
}

public function isDelivered(): bool
{
    return $this->status === OrderStatus::DELIVERED;
}

public function isCancelled(): bool
{
    return $this->status === OrderStatus::CANCELLED;
}

public function isPaid(): bool
{
    return $this->payment_status === PaymentStatus::PAID;
}
public function notificationOutboxes(): MorphMany
{
    return $this->morphMany(
        NotificationOutbox::class,
        'related'
    );
}
}