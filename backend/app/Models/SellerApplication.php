<?php

namespace App\Models;

use App\Enums\SellerApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'house_number',
        'description',
        'bonus_text',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellerApplicationStatus::class,
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where(
            'status',
            SellerApplicationStatus::PENDING
        );
    }
}