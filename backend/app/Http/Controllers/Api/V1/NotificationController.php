<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $service
    ) {}


public function index(Request $request): JsonResponse
{
    $validated = $request->validate([
        'status' => [
            'sometimes',
            'string',
            \Illuminate\Validation\Rule::in([
                'all',
                'unread',
                'read',
            ]),
        ],
        'per_page' => [
            'sometimes',
            'integer',
            'min:1',
            'max:50',
        ],
        'page' => [
            'sometimes',
            'integer',
            'min:1',
        ],
    ]);

    $notifications = $this->service->list(
        $request->user(),
        $validated
    );

    return response()->json([
        'success' => true,
        'data' => $notifications->items(),
        'meta' => [
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
        ],
    ]);
}


    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->service->unreadCount(
                $request->user()
            ),
        ]);
    }

    public function markAsRead(
        Request $request,
        int $id
    ): JsonResponse {
        $notification = $this->service->markAsRead(
            $request->user(),
            $id
        );

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $this->service->markAllAsRead(
            $request->user()
        );

        return response()->json([
            'message' => 'Notifications marked as read.',
            'updated_count' => $updated,
        ]);
    }
}