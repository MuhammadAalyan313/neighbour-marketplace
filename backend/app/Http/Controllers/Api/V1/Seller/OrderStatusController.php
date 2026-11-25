<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;

class OrderStatusController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $service
    ) {}

    public function update(
        UpdateOrderStatusRequest $request,
        Order $order
    ): JsonResponse {
        $order = $this->service->update(
            order: $order,
            seller: $request->user(),
            newStatus: OrderStatus::from(
                $request->validated('status')
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
        ]);
    }
}
