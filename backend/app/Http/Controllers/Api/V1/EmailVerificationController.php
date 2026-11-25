<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(
        private EmailOtpService $otpService
    ) {}

    public function send(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->is_active,
            403,
            'Account is inactive.'
        );

        $this->otpService->send($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        abort_unless(
            $request->user()->is_active,
            403,
            'Account is inactive.'
        );

        $verified = $this->otpService->verify(
            $request->user(),
            $validated['code']
        );

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }
}
