<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Services\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        protected UserManagementService $userManagementService
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = $this->userManagementService->getUsers(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ]);
    }
}
