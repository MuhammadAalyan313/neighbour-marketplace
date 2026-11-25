<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFilterRequest;
use App\Models\Store;
use App\Services\Admin\StoreManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        protected StoreManagementService $service
    ) {}

    public function index(StoreFilterRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getStores(
                $request->validated()
            ),
        ]);
    }

    public function show(Store $store): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getStore($store),
        ]);
    }

    public function updateStatus(
        Request $request,
        Store $store
    ): JsonResponse {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $store = $this->service->updateStatus(
            $store,
            $validated['is_active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Store status updated successfully.',
            'data' => $store,
        ]);
    }
}
