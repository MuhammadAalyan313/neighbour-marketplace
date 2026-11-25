<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CommissionType;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'commission_type',
        'commission_rate',
        'is_active',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_rate' => 'decimal:4',

            'commission_type' => CommissionType::class,

            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

     public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }

    public function hasCommission(): bool
    {
        return $this->commission_type !== CommissionType::NONE;
    }
}