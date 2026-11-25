<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {
    }

    public function store(
        CheckoutRequest $request
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Validated Data
            |--------------------------------------------------------------------------
            */

            $data = $request->validated();


            /*
            |--------------------------------------------------------------------------
            | Authenticated Buyer
            |--------------------------------------------------------------------------
            |
            | Guest checkout is also supported.
            | If user is not logged in, auth()->id() returns null.
            |
            */

            $buyerId = auth()->id();


            /*
            |--------------------------------------------------------------------------
            | Process Checkout
            |--------------------------------------------------------------------------
            */

            $batch = $this->checkoutService->checkout(
                data: $data,
                buyerId: $buyerId
            );


            /*
            |--------------------------------------------------------------------------
            | Success Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'Your order has been placed successfully.',

                'data' => [
                    'batch' => $batch,

                    'orders' => $batch->orders,
                ],
            ], 201);

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Log / Report Error
            |--------------------------------------------------------------------------
            */

            report($e);


            /*
            |--------------------------------------------------------------------------
            | Error Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => false,

                'message' =>
                    $this->getCheckoutErrorMessage($e),

            ], 422);
        }
    }


    /**
     * Return a safe checkout error message.
     */
    private function getCheckoutErrorMessage(
        Throwable $e
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Do not expose internal exceptions in production.
        |--------------------------------------------------------------------------
        */

        if (app()->environment('local')) {
            return $e->getMessage();
        }

        return 'Unable to process your order.';
    }
}