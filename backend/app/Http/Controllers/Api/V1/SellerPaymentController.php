<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\SellerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerPaymentController extends Controller
{
    public function __construct(
        private readonly SellerPaymentService $payments
    ) {}

    public function confirm(
        Request $request,
        Order $order
    ): JsonResponse {
        $validated = $request->validate([
            'payment_reference' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        $order = $this->payments->confirm(
            order: $order,
            seller: $request->user(),
            reference: $validated['payment_reference'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment marked as received.',
            'data' => new OrderResource($order),
        ]);
    }
}
