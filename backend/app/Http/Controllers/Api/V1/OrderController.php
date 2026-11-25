<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderIndexRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Authenticated buyer order history.
     */
    public function index(
        OrderIndexRequest $request
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        $orders = Order::query()
            ->with(['store', 'items'])
            ->where('buyer_id', $user->id)
            ->when(
                ! empty($validated['status']),
                fn ($query) => $query->where(
                    'status',
                    $validated['status']
                )
            )
            ->when(
                ! empty($validated['payment_status']),
                fn ($query) => $query->where(
                    'payment_status',
                    $validated['payment_status']
                )
            )
            ->when(
                isset($validated['store_id']),
                fn ($query) => $query->where(
                    'store_id',
                    $validated['store_id']
                )
            )
            ->when(
                ! empty($validated['from']),
                fn ($query) => $query->where(
                    'created_at',
                    '>=',
                    $validated['from'].' 00:00:00'
                )
            )
            ->when(
                ! empty($validated['to']),
                fn ($query) => $query->where(
                    'created_at',
                    '<=',
                    $validated['to'].' 23:59:59'
                )
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Authenticated buyer or guest with access token.
     */


public function show(
    Request $request,
    Order $order
): JsonResponse {
    $user = $request->user('sanctum');

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    if ($user !== null) {
        // Authenticated buyers can access only their own orders.
        abort_unless(
            $order->buyer_id !== null
                && (int) $order->buyer_id === (int) $user->id,
            403,
            'You are not authorized to view this order.'
        );
    } else {
        /*
        |--------------------------------------------------------------------------
        | Guest Authorization
        |--------------------------------------------------------------------------
        |
        | Guest tokens are valid only for guest-created orders.
        | Registered buyer orders require authentication.
        |
        */

        abort_unless(
            $order->buyer_id === null,
            403,
            'Registered buyer orders require authentication.'
        );

        $token = $request->query('access_token');

        abort_unless(
            is_string($token)
                && strlen($token) === 64
                && is_string($order->access_token)
                && strlen($order->access_token) === 64
                && hash_equals(
                    $order->access_token,
                    $token
                ),
            403,
            'Invalid order access token.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Order Details
    |--------------------------------------------------------------------------
    */

    $order->load([
        'store',
        'items',
        'review',
    ]);

    $method = strtolower(
        trim((string) $order->payment_method)
    );

    $isPaid = $order->payment_status === PaymentStatus::PAID;

    $isCancelled = $order->status === OrderStatus::CANCELLED;

    $instructions = null;

    /*
    |--------------------------------------------------------------------------
    | Payment Instructions
    |--------------------------------------------------------------------------
    */

    if (! $isPaid && ! $isCancelled) {
        if ($method === 'cash') {
            $instructions = [
                'method' => 'cash',
                'amount' => (string) $order->total_amount,
                'message' =>
                    'Pay cash directly to the seller on pickup or delivery.',
            ];
        }

        if (in_array(
            $method,
            ['easypaisa', 'jazzcash', 'bank_transfer'],
            true
        )) {
            // Preserve the account details from checkout time.
            $snapshot = $order->metadata[
                'payment_instructions'
            ] ?? null;

            if (
                is_array($snapshot)
                && ($snapshot['method'] ?? null) === $method
            ) {
                $account = $snapshot;
            } else {
                // Backward compatibility for older orders.
                $methods = $order->store?->payment_methods ?? [];

                $configured = $methods[$method] ?? null;

                $account = is_array($configured)
                    && ($configured['enabled'] ?? false) === true
                        ? $configured
                        : null;
            }

            if (is_array($account)) {
                $instructions = [
                    'method' => $method,
                    'amount' => (string) $order->total_amount,
                    'account_title' =>
                        $account['account_title'] ?? null,
                    'account_number' =>
                        $account['account_number'] ?? null,
                    'bank_name' =>
                        $account['bank_name'] ?? null,
                    'iban' =>
                        $account['iban'] ?? null,
                    'message' =>
                        'Transfer directly to the seller. The seller will confirm payment after verifying receipt.',
                ];
            }
        }
    }

    return response()->json([
        'success' => true,
        'data' => new OrderResource($order),
        'payment_instructions' => $instructions,
    ]);
}


}
