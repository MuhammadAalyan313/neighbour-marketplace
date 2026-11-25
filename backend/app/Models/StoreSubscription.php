<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\SubscriptionStatus;

class StoreSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'provider',
        'provider_subscription_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id'
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

 
public function isActive(): bool
{
    if (! in_array($this->status, [
        SubscriptionStatus::TRIAL,
        SubscriptionStatus::ACTIVE,
    ], true)) {
        return false;
    }

    if (
        $this->starts_at !== null &&
        $this->starts_at->isFuture()
    ) {
        return false;
    }

    if (
        $this->ends_at !== null &&
        $this->ends_at->lessThanOrEqualTo(now())
    ) {
        return false;
    }

    return true;
}

}