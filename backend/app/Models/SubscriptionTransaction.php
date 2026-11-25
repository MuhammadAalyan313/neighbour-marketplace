<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_subscription_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'provider',
        'provider_transaction_id',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            StoreSubscription::class,
            'store_subscription_id'
        );
    }
}