<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Services\SellerOrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerOrderController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly SellerOrderNotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store = $request->user()
            ->stores()
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'sometimes',
                Rule::in([
                    OrderStatus::PENDING->value,
                    OrderStatus::ACCEPTED->value,
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                ]),
            ],
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ]);

        $orders = Order::query()
            ->with(['store', 'items'])
            ->where('store_id', $store->id)
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where(
                    'status',
                    $validated['status']
                )
            )
            ->when(
                filled($validated['search'] ?? null),
                function ($query) use ($validated) {
                    $search = trim($validated['search']);

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where(
                                'order_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'buyer_name',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
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

    public function show(
        Request $request,
        Order $order
    ): JsonResponse {
        $ownsOrder = $request->user()
            ->stores()
            ->whereKey($order->store_id)
            ->exists();

        abort_unless(
            $ownsOrder,
            403,
            'You are not authorized to view this order.'
        );

        $order->load([
            'store.owner',
            'buyer',
            'items',
        ]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
            'actions' => [
                'whatsapp_url' =>
                    $this->notificationService
                        ->whatsappUrl($order),

                'buyer_receipt_whatsapp_url' =>
                    $this->notificationService
                        ->buyerReceiptWhatsappUrl($order),
            ],
        ]);
    }


public function updateStatus(
    Request $request,
    Order $order
): JsonResponse {
    $validated = $request->validate([
        'status' => [
            'required',
            Rule::in([
                OrderStatus::ACCEPTED->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
            ]),
        ],
        'cancellation_reason' => [
            'sometimes',
            'nullable',
            'string',
            'max:1000',
        ],
        'payment_received' => [
            'sometimes',
            'boolean',
        ],
        'payment_reference' => [
            'sometimes',
            'nullable',
            'string',
            'max:150',
        ],
    ]);

    $paymentReceived = $request->boolean('payment_received');

    // Payment confirmation is combined only with delivery.
    if (
        $paymentReceived &&
        $validated['status'] !== OrderStatus::DELIVERED->value
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'Payment can be confirmed here only when delivering the order.',
        ], 422);
    }

    $updatedOrder = $this->orderStatusService->update(
        order: $order,
        seller: $request->user(),
        newStatus: OrderStatus::from($validated['status']),
        cancellationReason:
            $validated['cancellation_reason'] ?? null,
        paymentReceived: $paymentReceived,
        paymentReference:
            $validated['payment_reference'] ?? null
    );

    $updatedOrder->loadMissing([
        'store',
        'items',
    ]);

    return response()->json([
        'success' => true,
        'message' => $paymentReceived
            ? 'Order updated and payment confirmed successfully.'
            : 'Order status updated successfully.',
        'data' => new OrderResource($updatedOrder),
    ]);
}

}
