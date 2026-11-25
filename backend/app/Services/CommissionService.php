<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Models\SubscriptionPlan;
use App\Support\Money;

class CommissionService
{
    public function calculate(
        SubscriptionPlan $plan,
        string|int|float $amount
    ): array {
        $amountMinor = Money::toMinor($amount);

        if ($plan->commission_type === CommissionType::NONE) {
            return [
                'fee' => '0.00',
                'rate' => '0.0000',
            ];
        }

        if (
            $plan->commission_type ===
            CommissionType::PERCENTAGE
        ) {
            $rate = (float) $plan->commission_rate;

            $feeMinor = (int) round(
                $amountMinor * ($rate / 100)
            );

            return [
                'fee' => Money::fromMinor($feeMinor),
                'rate' => (string) $plan->commission_rate,
            ];
        }

        if (
            $plan->commission_type ===
            CommissionType::FIXED
        ) {
            $feeMinor = Money::toMinor(
                $plan->commission_rate
            );

            return [
                'fee' => Money::fromMinor($feeMinor),
                'rate' => '0.0000',
            ];
        }

        return [
            'fee' => '0.00',
            'rate' => '0.0000',
        ];
    }
}