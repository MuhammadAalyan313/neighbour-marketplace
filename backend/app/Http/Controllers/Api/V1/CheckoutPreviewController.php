<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutPreviewRequest;
use App\Services\CheckoutPreviewService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CheckoutPreviewController extends Controller
{
    public function __construct(
        protected CheckoutPreviewService $previewService
    ) {
    }

    public function store(
        CheckoutPreviewRequest $request
    ): JsonResponse {

        try {

            $preview = $this->previewService->preview(
                items: $request->validated('items'),
                fulfillmentType: $request->validated(
                    'fulfillment_type'
                ),
            );

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);

        } catch (RuntimeException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}