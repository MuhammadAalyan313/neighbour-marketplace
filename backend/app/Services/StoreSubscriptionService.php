<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use Illuminate\Support\Facades\DB;
use App\Models\SubscriptionPlan;


class StoreSubscriptionService
{
   
public function active(Store $store): ?StoreSubscription
{
    $now = now();

    return $store->subscriptions()
        ->with('plan')
        ->whereIn('status', [
            SubscriptionStatus::TRIAL->value,
            SubscriptionStatus::ACTIVE->value,
        ])
        ->where(function ($query) use ($now) {
            $query->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now);
        })
        ->where(function ($query) use ($now) {
            $query->whereNull('ends_at')
                ->orWhere('ends_at', '>', $now);
        })
        ->latest('id')
        ->first();
}


    public function hasActiveSubscription(Store $store): bool
    {
        return $this->active($store) !== null;
    }
    

public function cancel(Store $store): StoreSubscription
{
    return DB::transaction(function () use ($store) {
        // Lock store to prevent concurrent subscription changes.
        $lockedStore = Store::query()
            ->whereKey($store->id)
            ->lockForUpdate()
            ->firstOrFail();

        $now = now();

        $subscription = $lockedStore->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::TRIAL->value,
                SubscriptionStatus::ACTIVE->value,
            ])
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            })
            ->latest('id')
            ->lockForUpdate()
            ->first();

        abort_unless(
            $subscription,
            409,
            'No active subscription available to cancel.'
        );

        // Provider-managed subscriptions require provider cancellation.
        abort_if(
            $subscription->provider_subscription_id !== null,
            409,
            'This subscription must be cancelled through its payment provider.'
        );

        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => $now,
        ]);

        return $subscription->fresh(['plan']);
    }, 3);
}



public function activateFreePlan(
    Store $store,
    SubscriptionPlan $plan
): StoreSubscription {
    return DB::transaction(function () use ($store, $plan) {

        // Prevent concurrent subscription activations.
        $store = Store::query()
            ->whereKey($store->id)
            ->lockForUpdate()
            ->firstOrFail();

        $plan = SubscriptionPlan::query()
            ->whereKey($plan->id)
            ->firstOrFail();

        abort_unless(
            $store->is_active,
            403,
            'Store is inactive.'
        );

        abort_unless(
            $plan->is_active,
            422,
            'This subscription plan is unavailable.'
        );

        // Only zero-price plans can be activated without payment.
        abort_unless(
            is_numeric($plan->price)
                && (float) $plan->price == 0.0,
            422,
            'Only free subscription plans can be activated.'
        );

        $now = now();

        // Same validity rules as active() and cancel().
        $hasActiveSubscription = $store->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIAL->value,
            ])
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            })
            ->exists();

        abort_if(
            $hasActiveSubscription,
            409,
            'Store already has an active subscription.'
        );

        $startsAt = $now->copy();

        $interval = $plan->billing_interval instanceof \BackedEnum
            ? $plan->billing_interval->value
            : $plan->billing_interval;

        $endsAt = match ($interval) {
            'monthly' => $startsAt->copy()->addMonthNoOverflow(),
            'yearly' => $startsAt->copy()->addYearNoOverflow(),
            default => abort(
                422,
                'Unsupported subscription billing interval.'
            ),
        };

        return $store->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'provider' => null,
            'provider_subscription_id' => null,
            'metadata' => [
                'activation_type' => 'free_self_service',
                'price_at_activation' => '0.00',
                'currency' => $plan->currency,
            ],
        ])->load('plan');

    }, 3);
}



}