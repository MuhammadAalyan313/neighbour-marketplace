<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Services\StoreRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct(
        protected StoreRatingService $ratingService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Store Reviews
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request,
        Store $store
    ): JsonResponse {
        $reviews = Review::query()
            ->with([
                'user:id,name',
            ])
            ->where('store_id', $store->id)
            ->where('is_published', true)
            ->latest('created_at')
            ->latest('id')
            ->paginate(
                max(1, min(
                    $request->integer('per_page', 20),
                    50
                ))
            );

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Review
    |--------------------------------------------------------------------------
    */

 

public function store(
    Request $request,
    Order $order
): JsonResponse {
    $user = $request->user();

    $validated = $request->validate([
        'rating' => [
            'required',
            'integer',
            'between:1,5',
        ],
        'comment' => [
            'nullable',
            'string',
            'max:2000',
        ],
    ]);

    $review = DB::transaction(function () use (
        $order,
        $user,
        $validated
    ) {
        // Lock order to prevent duplicate reviews.
        $lockedOrder = Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(
            (int) $lockedOrder->buyer_id === (int) $user->id,
            403,
            'You are not authorized to review this order.'
        );

        abort_unless(
            $lockedOrder->status === OrderStatus::DELIVERED,
            422,
            'Only delivered orders can be reviewed.'
        );

        // Lock store to serialize rating recalculations.
        Store::query()
            ->whereKey($lockedOrder->store_id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_if(
            Review::query()
                ->where('order_id', $lockedOrder->id)
                ->exists(),
            409,
            'This order has already been reviewed.'
        );

        $review = Review::query()->create([
            'order_id' => $lockedOrder->id,
            'store_id' => $lockedOrder->store_id,
            'buyer_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_published' => true,
        ]);

        // Keep rating calculation in the same transaction.
        $this->ratingService->recalculate(
            $lockedOrder->store_id
        );

        return $review;
    }, 3);

    $review->load('user:id,name');

    return response()->json([
        'success' => true,
        'message' => 'Review submitted successfully.',
        'data' => new ReviewResource($review),
    ], 201);
}


    /*
    |--------------------------------------------------------------------------
    | Update Review
    |--------------------------------------------------------------------------
    */

  
public function update(
    Request $request,
    Review $review
): JsonResponse {
    $user = $request->user();

    abort_unless(
        (int) $review->buyer_id === (int) $user->id,
        403,
        'You are not authorized to update this review.'
    );

    $validated = $request->validate([
        'rating' => [
            'sometimes',
            'integer',
            'min:1',
            'max:5',
        ],
        'comment' => [
            'sometimes',
            'nullable',
            'string',
            'max:2000',
        ],
    ]);

    $review = DB::transaction(function () use (
        $review,
        $user,
        $validated
    ) {
        // Serialize rating recalculations for this store.
        Store::query()
            ->whereKey($review->store_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedReview = Review::query()
            ->whereKey($review->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(
            (int) $lockedReview->buyer_id === (int) $user->id,
            403,
            'You are not authorized to update this review.'
        );

        $lockedReview->update($validated);

        $this->ratingService->recalculate(
            $lockedReview->store_id
        );

        return $lockedReview->fresh(['user:id,name']);
    }, 3);

    return response()->json([
        'success' => true,
        'message' => 'Review updated successfully.',
        'data' => new ReviewResource($review),
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Delete Review
    |--------------------------------------------------------------------------
    */

 
public function destroy(
    Request $request,
    Review $review
): JsonResponse {
    $user = $request->user();

    abort_unless(
        (int) $review->buyer_id === (int) $user->id,
        403,
        'You are not authorized to delete this review.'
    );

    DB::transaction(function () use ($review, $user) {
        Store::query()
            ->whereKey($review->store_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedReview = Review::query()
            ->whereKey($review->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(
            (int) $lockedReview->buyer_id === (int) $user->id,
            403,
            'You are not authorized to delete this review.'
        );

        $storeId = $lockedReview->store_id;

        $lockedReview->delete();

        $this->ratingService->recalculate($storeId);
    }, 3);

    return response()->json([
        'success' => true,
        'message' => 'Review deleted successfully.',
    ]);
}

}