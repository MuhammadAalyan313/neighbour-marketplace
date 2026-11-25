<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category_id',
        'title',
        'description',
        'price',
        'image',
        'badge',
        'is_available',
        'stock_quantity',
        'sales_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'stock_quantity' => 'integer',
            'sales_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeAvailable($query)
    {
        return $query
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('stock_quantity')
                    ->orWhere('stock_quantity', '>', 0);
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isAvailable(): bool
    {
        return $this->is_available
            && (
                $this->stock_quantity === null
                || $this->stock_quantity > 0
            );
    }

    public function hasEnoughStock(int $quantity): bool
    {
        return $quantity > 0
            && $this->isAvailable()
            && (
                $this->stock_quantity === null
                || $this->stock_quantity >= $quantity
            );
    }
}
