<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\UserRole;
use App\Models\SellerApplication;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\NotificationOutbox;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'house_number',
        'role',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function checkoutBatches(): HasMany
    {
        return $this->hasMany(CheckoutBatch::class, 'buyer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'buyer_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::SELLER;
    }

    public function isBuyer(): bool
    {
        return $this->role === UserRole::BUYER;
    }
    public function sellerApplications(): HasMany
{
    return $this->hasMany(SellerApplication::class);
}
public function notificationOutboxes(): MorphMany
{
    return $this->morphMany(
        NotificationOutbox::class,
        'notifiable'
    );
}
}