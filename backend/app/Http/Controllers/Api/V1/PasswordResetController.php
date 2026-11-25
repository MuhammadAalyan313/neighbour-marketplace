<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function __construct(
        private EmailOtpService $otpService
    ) {}

  
public function sendOtp(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => [
            'required',
            'email',
            'max:255',
        ],
    ]);

    $message = 'If this email has an active account, '
        .'a reset code will be sent.';

    $user = User::query()
        ->where('email', $validated['email'])
        ->where('is_active', true)
        ->first();

    if ($user) {
        try {
            $this->otpService->sendPasswordReset($user);
        } catch (
            \Illuminate\Validation\ValidationException $exception
        ) {
            // Do not reveal whether a particular account
            // has already requested a reset code.
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error(
                'Password reset email dispatch failed.',
                [
                    'user_id' => $user->id,
                    'exception' => get_class($exception),
                ]
            );

            // Keep public response generic.
        }
    }

    return response()->json([
        'success' => true,
        'message' => $message,
    ]);
}


    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        $success = $user
            ? $this->otpService->resetPassword(
                $user,
                $validated['code'],
                $validated['password']
            )
            : false;

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Password changed successfully. Please log in again.',
        ]);
    }
}
