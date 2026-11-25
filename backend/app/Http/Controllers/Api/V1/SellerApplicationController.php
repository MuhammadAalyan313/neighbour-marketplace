<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerApplicationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $application = SellerApplication::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $application) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No seller application found.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin users cannot submit seller applications.',
            ], 403);
        }

        if ($user->isSeller()) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a seller.',
            ], 422);
        }

        $hasPendingApplication = SellerApplication::query()
            ->where('user_id', $user->id)
            ->where('status', SellerApplicationStatus::PENDING)
            ->exists();

        if ($hasPendingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending seller application.',
            ], 422);
        }

        $validated = $request->validate([
            'store_name' => [
                'required',
                'string',
                'max:150',
            ],

            'store_slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:seller_applications,store_slug',
                'unique:stores,slug',
            ],

            'house_number' => [
                'required',
                'string',
                'max:100',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'bonus_text' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $application = DB::transaction(function () use ($user, $validated) {
            return SellerApplication::create([
                'user_id' => $user->id,
                'store_name' => $validated['store_name'],
                'store_slug' => strtolower($validated['store_slug']),
                'house_number' => $validated['house_number'],
                'description' => $validated['description'] ?? null,
                'bonus_text' => $validated['bonus_text'] ?? null,
                'status' => SellerApplicationStatus::PENDING,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Seller application submitted successfully.',
            'data' => $application,
        ], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        $application = SellerApplication::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No seller application found.',
            ], 404);
        }

        if ($application->status !== SellerApplicationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending applications can be cancelled.',
            ], 422);
        }

        $application->update([
            'status' => SellerApplicationStatus::CANCELLED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller application cancelled.',
            'data' => $application->fresh(),
        ]);
    }
}