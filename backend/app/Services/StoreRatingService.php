<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class StoreRatingService
{
    public function recalculate(int $storeId): void
    {
        $rating = Review::query()
            ->where('store_id', $storeId)
            ->where('is_published', true)
            ->selectRaw(
                'COALESCE(AVG(rating), 0) as average_rating'
            )
            ->selectRaw(
                'COUNT(*) as review_count'
            )
            ->first();

        Store::query()
            ->whereKey($storeId)
            ->update([
                'rating_average' => number_format(
                    (float) $rating->average_rating,
                    2,
                    '.',
                    ''
                ),

                'reviews_count' =>
                    (int) $rating->review_count,
            ]);
    }
}