<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSellerApplicationsRequest;
use App\Http\Requests\Admin\RejectSellerApplicationRequest;
use App\Models\SellerApplication;
use App\Services\Admin\SellerApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerApplicationController extends Controller
{
    public function __construct(
        protected SellerApprovalService $service
    ) {}

    public function index(
        ListSellerApplicationsRequest $request
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $this->service->listApplications(
                $request->validated()
            ),
        ]);
    }

    public function approve(
        Request $request,
        SellerApplication $application
    ): JsonResponse {
        $application = $this->service->approve(
            $application,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Seller application approved.',
            'data' => $application,
        ]);
    }

    public function reject(
        RejectSellerApplicationRequest $request,
        SellerApplication $application
    ): JsonResponse {
        $application = $this->service->reject(
            $application,
            $request->user()->id,
            $request->validated('rejection_reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Seller application rejected.',
            'data' => $application,
        ]);
    }
}
