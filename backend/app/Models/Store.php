<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'house_number',
        'is_open',
        'is_active',
        'bonus_text',
        'description',
        'logo',
        'rating_average',
        'reviews_count',
        'payment_methods',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',

            'is_active' => 'boolean',

            'rating_average' => 'decimal:2',

            'reviews_count' => 'integer',
            
            'payment_methods' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Owner
    |--------------------------------------------------------------------------
    */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    */

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            StoreSubscription::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    public function reviews(): HasMany
    {
        return $this->hasMany(
            Review::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Status
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return $this->is_open
            && $this->is_active;
    }

    /*
    |--------------------------------------------------------------------------
    | Available Products
    |--------------------------------------------------------------------------
    */

    public function availableProducts(): HasMany
    {
        return $this->products()
            ->where('is_available', true);
    }
}