<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Services\PushSubscriptionService;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private PushSubscriptionService $service
    ) {}

    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $this->service->subscribe(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Push subscription registered successfully.',
        ], 201);
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        $this->service->unsubscribe(
            $request->user(),
            $request->validated('endpoint')
        );

        return response()->json([
            'message' => 'Push subscription removed successfully.',
        ]);
    }
}