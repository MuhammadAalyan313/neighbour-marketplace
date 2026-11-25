<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address first.',
                'code' => 'EMAIL_NOT_VERIFIED',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
