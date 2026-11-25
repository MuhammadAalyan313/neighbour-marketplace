<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CheckoutBatchResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        // Checkout supports authenticated buyers and guests.
        $buyerId = auth('sanctum')->id();

        try {
            $batch = $this->checkoutService->checkout(
                data: $request->validated(),
                buyerId: $buyerId
            );

     
// Ensure orders are loaded for the response.
$batch->loadMissing([
    'orders.items',
    'orders.store',
]);

$response = [
    'success' => true,
    'message' => 'Your order has been placed successfully.',
    'data' => new CheckoutBatchResource($batch),

    // Confirmed order data se WhatsApp receipt links.
    'whatsapp_receipts' => $batch->orders
        ->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'whatsapp_url' => app(
                    \App\Services\OrderReceiptService::class
                )->whatsappUrl($order),
            ];
        })
        ->values()
        ->all(),
];


            // Guest tracking credentials must only be returned
            // through the authorized checkout response.
            if ($buyerId === null) {
                $response['guest_tracking'] = $batch->orders
                    ->map(function ($order) {
                        return [
                            'order_number' => $order->order_number,
                            'access_token' => $order->access_token,
                        ];
                    })
                    ->values()
                    ->all();
            }

            return response()->json($response, 201);

        } catch (HttpExceptionInterface $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
                    ?: 'Checkout request could not be completed.',
            ], $e->getStatusCode());

        } catch (Throwable $e) {
            Log::error('Checkout failed.', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'buyer_id' => $buyerId,
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Unable to process your order. Please try again.',
            ], 500);
        }
    }
}
