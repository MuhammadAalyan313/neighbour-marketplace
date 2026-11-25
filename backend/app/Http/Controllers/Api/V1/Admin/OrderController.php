<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderFilterRequest;
use App\Models\Order;
use App\Services\Admin\OrderManagementService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderManagementService $service
    ) {}

    public function index(OrderFilterRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $this->service->getOrders(
                $request->validated()
            ),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully.',
            'data' => $this->service->getOrder($order),
        ]);
    }
}
