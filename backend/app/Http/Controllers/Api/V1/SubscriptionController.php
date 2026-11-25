<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\StoreSubscriptionService;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => (string) $plan->price,
                    'currency' => $plan->currency,
                    'billing_interval' => $plan->billing_interval,
                    'commission_type' =>
                        $plan->commission_type?->value,
                    'commission_rate' =>
                        (string) $plan->commission_rate,
                    'features' => $plan->features,
                ];
            }),
        ]);
    }

   
public function show(Request $request): JsonResponse
{
    $store = $request->user()
        ->stores()
        ->where('is_active', true)
        ->first();

    if (! $store) {
        return response()->json([
            'success' => false,
            'message' => 'Store not found.',
        ], 404);
    }

    $subscriptionService = app(
        \App\Services\StoreSubscriptionService::class
    );

    // Prefer the subscription currently granting access.
    $activeSubscription = $subscriptionService->active($store);

    // Preserve the latest subscription for history/renewal UI.
    $latestSubscription = $store->subscriptions()
        ->with('plan')
        ->latest('id')
        ->first();

    $subscription = $activeSubscription ?? $latestSubscription;

    $isActive = $activeSubscription !== null;

    $status = $subscription?->status;

    $now = now();

    $isExpired = $subscription !== null
        && $subscription->ends_at !== null
        && $subscription->ends_at->lessThanOrEqualTo($now)
        && in_array(
            $status,
            [
                \App\Enums\SubscriptionStatus::ACTIVE,
                \App\Enums\SubscriptionStatus::TRIAL,
            ],
            true
        );

    $effectiveStatus = $isExpired
        ? \App\Enums\SubscriptionStatus::EXPIRED->value
        : $status?->value;

    $canRenew = ! $isActive && (
        $isExpired ||
        in_array(
            $status,
            [
                \App\Enums\SubscriptionStatus::EXPIRED,
                \App\Enums\SubscriptionStatus::CANCELLED,
            ],
            true
        )
    );

    return response()->json([
        'success' => true,

        'data' => [
            'store_id' => $store->id,

            'has_active_subscription' => $isActive,

            'subscription' => $subscription
                ? [
                    'id' => $subscription->id,

                    'status' => $status?->value,

                    'effective_status' => $effectiveStatus,

                    'is_active' => $isActive,

                    'can_renew' => $canRenew,

                    'starts_at' =>
                        $subscription->starts_at?->toISOString(),

                    'ends_at' =>
                        $subscription->ends_at?->toISOString(),

                    'cancelled_at' =>
                        $subscription->cancelled_at?->toISOString(),

                    'plan' => $subscription->plan
                        ? [
                            'id' => $subscription->plan->id,

                            'name' => $subscription->plan->name,

                            'slug' => $subscription->plan->slug,

                            'description' =>
                                $subscription->plan->description,

                            'price' =>
                                (string) $subscription->plan->price,

                            'currency' =>
                                $subscription->plan->currency,

                            'billing_interval' =>
                                $subscription->plan->billing_interval,

                            'commission_type' =>
                                $subscription->plan
                                    ->commission_type?->value,

                            'commission_rate' =>
                                (string) $subscription->plan
                                    ->commission_rate,

                            'features' =>
                                $subscription->plan->features,
                        ]
                        : null,
                ]
                : null,
        ],
    ]);
}

    
public function cancel(
    Request $request,
    StoreSubscriptionService $subscriptionService
): JsonResponse {
    $store = $request->user()
        ->stores()
        ->where('is_active', true)
        ->first();

    if (! $store) {
        return response()->json([
            'success' => false,
            'message' => 'Active store not found.',
        ], 404);
    }

    $subscription = $subscriptionService->cancel($store);

    return response()->json([
        'success' => true,
        'message' => 'Subscription cancelled successfully.',
        'data' => [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'cancelled_at' => $subscription->cancelled_at
                ?->toISOString(),
            'plan' => $subscription->plan,
        ],
    ]);
}

public function activateFree(
    Request $request,
    StoreSubscriptionService $subscriptionService
): JsonResponse {
    $validated = $request->validate([
        'plan_id' => [
            'required',
            'integer',
            'exists:subscription_plans,id',
        ],
    ]);

    abort_unless(
        $request->user()?->is_active
            && $request->user()->isSeller(),
        403,
        'Only active sellers can activate subscriptions.'
    );

    $store = $request->user()
        ->stores()
        ->where('is_active', true)
        ->first();

    if (! $store) {
        return response()->json([
            'success' => false,
            'message' => 'Active store not found.',
        ], 404);
    }

    $plan = SubscriptionPlan::query()
        ->findOrFail($validated['plan_id']);

    $subscription = $subscriptionService->activateFreePlan(
        $store,
        $plan
    );

    return response()->json([
        'success' => true,
        'message' => 'Free subscription activated successfully.',
        'data' => [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'price' => (string) $subscription->plan->price,
                'currency' => $subscription->plan->currency,
            ],
        ],
    ], 201);
}


}