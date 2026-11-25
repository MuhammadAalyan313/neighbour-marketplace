<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutBatchResource;
use App\Models\CheckoutBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutBatchController extends Controller
{

public function show(
    Request $request,
    CheckoutBatch $checkoutBatch
): JsonResponse {
    $user = $request->user('sanctum');

    /*
    |--------------------------------------------------------------------------
    | Authenticated Buyer
    |--------------------------------------------------------------------------
    */

    if (
        $user !== null
        && $checkoutBatch->buyer_id !== null
        && (int) $checkoutBatch->buyer_id === (int) $user->id
    ) {
        $checkoutBatch->load([
            'orders.store',
            'orders.items',
        ]);

        return response()->json([
            'success' => true,
            'data' => new CheckoutBatchResource(
                $checkoutBatch
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guest Access
    |--------------------------------------------------------------------------
    |
    | An order token authorizes only its own order.
    | It must not expose every order in a checkout batch.
    |
    */

    // Registered buyers must use authenticated ownership.
    // Guest tokens are only for guest-created batches.
    abort_if(
        $checkoutBatch->buyer_id !== null,
        403,
        'You are not authorized to view this checkout.'
    );

    $token = $request->query('access_token');

    abort_unless(
        is_string($token)
            && strlen($token) === 64,
        403,
        'A valid order access token is required.'
    );

    $matchedOrder = $checkoutBatch->orders()
        ->where('access_token', $token)
        ->first();

    abort_unless(
        $matchedOrder !== null
            && is_string($matchedOrder->access_token)
            && hash_equals(
                $matchedOrder->access_token,
                $token
            ),
        403,
        'Invalid order access token.'
    );

    /*
    |--------------------------------------------------------------------------
    | Return Only Authorized Guest Order
    |--------------------------------------------------------------------------
    */

    $matchedOrder->load([
        'store',
        'items',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Guest order retrieved successfully.',
        'data' => new \App\Http\Resources\OrderResource(
            $matchedOrder
        ),
    ]);
}

}