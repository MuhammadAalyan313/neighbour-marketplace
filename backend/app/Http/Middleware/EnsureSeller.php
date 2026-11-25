<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::SELLER) {
            return response()->json([
                'success' => false,
                'message' => 'Seller access required.',
            ], 403);
        }

        return $next($request);
    }
}
