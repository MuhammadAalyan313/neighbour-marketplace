<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'max:30',
                'unique:users,phone',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'house_number' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'house_number' => $validated['house_number'] ?? null,
            'role' => UserRole::BUYER,
            'is_active' => true,
        ]);

        $token = $user->createToken(
            'marketplace-api'
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verified' =>
                    $user->email_verified_at !== null,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        $token = $user->createToken(
            'marketplace-api'
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verified' =>
                    $user->email_verified_at !== null,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'email_verified' =>
                    $user->email_verified_at !== null,
            ],
        ]);
    }


public function logout(Request $request): JsonResponse
{
    $user = $request->user();

    $token = $user->currentAccessToken();

    if (
        ! $token instanceof
        \Laravel\Sanctum\PersonalAccessToken
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'No revocable API token was found for this session.',
        ], 422);
    }

    $token->delete();

    return response()->json([
        'success' => true,
        'message' => 'Logged out successfully.',
    ]);
}

    
public function logoutAll(Request $request): JsonResponse
{
    $user = $request->user();

    // Revoke all Sanctum personal access tokens.
    // Existing device sessions will require login again.
    $deletedTokens = $user->tokens()->delete();

    return response()->json([
        'success' => true,
        'message' => 'Logged out from all API devices successfully.',
        'data' => [
            'revoked_tokens' => $deletedTokens,
        ],
    ]);
}



public function sessions(Request $request): JsonResponse
{
    $validated = $request->validate([
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

    $user = $request->user();

    $currentToken = $user->currentAccessToken();

    $currentTokenId = $currentToken instanceof
        \Laravel\Sanctum\PersonalAccessToken
            ? $currentToken->getKey()
            : null;

    $sessions = $user->tokens()
        ->select([
            'id',
            'name',
            'created_at',
            'last_used_at',
            'expires_at',
        ])
        ->orderByDesc('last_used_at')
        ->orderByDesc('id')
        ->paginate($validated['per_page'] ?? 20);

    $data = $sessions->getCollection()
        ->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' =>
                    $token->created_at?->toISOString(),
                'last_used_at' =>
                    $token->last_used_at?->toISOString(),
                'expires_at' =>
                    $token->expires_at?->toISOString(),
                'is_current' =>
                    $currentTokenId !== null
                    && (int) $token->id ===
                        (int) $currentTokenId,
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'data' => $data,
        'meta' => [
            'current_page' => $sessions->currentPage(),
            'last_page' => $sessions->lastPage(),
            'per_page' => $sessions->perPage(),
            'total' => $sessions->total(),
        ],
    ]);
}


public function revokeSession(
    Request $request,
    int $tokenId
): JsonResponse {
    $user = $request->user();

    // Token must belong to the authenticated user.
    $token = $user->tokens()
        ->whereKey($tokenId)
        ->firstOrFail();

    $currentTokenId = $user->currentAccessToken()?->getKey();

    if (
        $currentTokenId !== null
        && (int) $token->id === (int) $currentTokenId
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'Use the logout endpoint to revoke your current session.',
        ], 422);
    }

    $token->delete();

    return response()->json([
        'success' => true,
        'message' => 'Selected session logged out successfully.',
    ]);
}


}
