<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Models\Product;
use App\Support\Money;
use RuntimeException;

class CheckoutPreviewService
{
    public function __construct(
        protected CommissionService $commissionService,
        protected DeliveryFeeService $deliveryFeeService,
        protected DiscountService $discountService,
        protected StoreSubscriptionService $subscriptionService,
    ) {
    }

  

public function preview(
    array $items,
    FulfillmentType|string $fulfillmentType
): array {
    if (! $fulfillmentType instanceof FulfillmentType) {
        $fulfillmentType = FulfillmentType::from(
            $fulfillmentType
        );
    }

    if (empty($items)) {
        throw new RuntimeException(
            'Checkout must contain at least one item.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Products
    |--------------------------------------------------------------------------
    */

    $requestedQuantities = collect($items)
        ->groupBy('product_id')
        ->map(fn ($group) => $group->sum(
            fn ($item) => (int) $item['quantity']
        ));

    $productIds = $requestedQuantities
        ->keys()
        ->values();

    $products = Product::query()
        ->with(['store', 'category'])
        ->whereIn('id', $productIds)
        ->get()
        ->keyBy('id');

    if ($products->count() !== $productIds->count()) {
        throw new RuntimeException(
            'One or more products could not be found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Products + Aggregate Stock
    |--------------------------------------------------------------------------
    */

    foreach ($requestedQuantities as $productId => $quantity) {
        $product = $products->get($productId);

        if (! $product) {
            throw new RuntimeException(
                "Product {$productId} not found."
            );
        }

        if ($quantity <= 0) {
            throw new RuntimeException(
                "Invalid quantity for product {$productId}."
            );
        }

        if (! $product->is_available) {
            throw new RuntimeException(
                "Product '{$product->title}' is currently unavailable."
            );
        }

        if (
            $product->stock_quantity !== null
            && (int) $product->stock_quantity < $quantity
        ) {
            throw new RuntimeException(
                "Insufficient stock for '{$product->title}'."
            );
        }

        if (! $product->store) {
            throw new RuntimeException(
                "Store for '{$product->title}' was not found."
            );
        }

        if (! $product->store->is_active) {
            throw new RuntimeException(
                "Store '{$product->store->store_name}' is inactive."
            );
        }

        if (! $product->store->isOpen()) {
            throw new RuntimeException(
                "Store '{$product->store->store_name}' is currently closed."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Group By Store
    |--------------------------------------------------------------------------
    */

    $itemsByStore = collect($items)
        ->groupBy(
            fn (array $item) =>
                $products->get($item['product_id'])->store_id
        );

    $stores = [];

    $grandSubtotalMinor = 0;
    $grandDiscountMinor = 0;
    $grandDeliveryMinor = 0;
    $grandPlatformFeeMinor = 0;
    $grandTotalMinor = 0;

    /*
    |--------------------------------------------------------------------------
    | Payment Methods Available Across All Stores
    |--------------------------------------------------------------------------
    */

    $digitalMethods = [
        'easypaisa',
        'jazzcash',
        'bank_transfer',
    ];

    // Cash is always supported at launch.
    $availablePaymentMethods = [
        'cash',
        ...$digitalMethods,
    ];

    /*
    |--------------------------------------------------------------------------
    | Calculate Each Store Order
    |--------------------------------------------------------------------------
    */

    foreach ($itemsByStore as $storeId => $storeItems) {
        $firstItem = $storeItems->first();

        $store = $products
            ->get($firstItem['product_id'])
            ->store;

        /*
        |--------------------------------------------------------------------------
        | Payment Availability
        |--------------------------------------------------------------------------
        */

        $configuredMethods = $store->payment_methods ?? [];

        $storePaymentMethods = ['cash'];

        foreach ($digitalMethods as $method) {
            $account = $configuredMethods[$method] ?? null;

            if (
                is_array($account)
                && ($account['enabled'] ?? false) === true
            ) {
                $storePaymentMethods[] = $method;
            }
        }

        // Only methods supported by every store.
        $availablePaymentMethods = array_values(
            array_intersect(
                $availablePaymentMethods,
                $storePaymentMethods
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Store Items + Subtotal
        |--------------------------------------------------------------------------
        */

        $subtotalMinor = 0;
        $previewItems = [];

        foreach ($storeItems as $item) {
            $product = $products->get(
                $item['product_id']
            );

            $quantity = (int) $item['quantity'];

            $itemSubtotalMinor = Money::multiply(
                $product->price,
                $quantity
            );

            $subtotalMinor += $itemSubtotalMinor;

            $previewItems[] = [
                'product_id' => $product->id,
                'title' => $product->title,
                'image' => $product->image,
                'quantity' => $quantity,
                'unit_price' => (string) $product->price,
                'subtotal' => Money::fromMinor(
                    $itemSubtotalMinor
                ),
            ];
        }

        $subtotal = Money::fromMinor($subtotalMinor);

        /*
        |--------------------------------------------------------------------------
        | Discount + Delivery
        |--------------------------------------------------------------------------
        */

        $discount = $this->discountService->calculate(
            cartItems: $storeItems->values()->all(),
            storeId: (int) $storeId
        );

        $deliveryFee = $this->deliveryFeeService->calculate(
            fulfillmentType: $fulfillmentType,
            storeId: (int) $storeId
        );

        /*
        |--------------------------------------------------------------------------
        | Platform Commission
        |--------------------------------------------------------------------------
        |
        | Commission disabled during initial launch.
        | Existing subscription-based calculation preserved.
        |
        */

        $platformFee = '0.00';
        $platformFeeRate = '0.0000';

        if (
            config(
                'marketplace.platform_commission_enabled',
                false
            )
        ) {
            $subscription = $this->subscriptionService
                ->active($store);

            if ($subscription && $subscription->plan) {
                $commission = $this->commissionService->calculate(
                    plan: $subscription->plan,
                    amount: $subtotal
                );

                $platformFee = $commission['fee'];
                $platformFeeRate = $commission['rate'];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $totalMinor = Money::toMinor($subtotal)
            - Money::toMinor($discount)
            + Money::toMinor($deliveryFee)
            + Money::toMinor($platformFee);

        if ($totalMinor < 0) {
            throw new RuntimeException(
                'Store order total cannot be negative.'
            );
        }

        $grandSubtotalMinor += $subtotalMinor;

        $grandDiscountMinor += Money::toMinor($discount);

        $grandDeliveryMinor += Money::toMinor($deliveryFee);

        $grandPlatformFeeMinor += Money::toMinor($platformFee);

        $grandTotalMinor += $totalMinor;

        /*
        |--------------------------------------------------------------------------
        | Store Preview
        |--------------------------------------------------------------------------
        */

        $stores[] = [
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'store_slug' => $store->slug,
            'house_number' => $store->house_number,

            'items' => $previewItems,

            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'platform_fee' => $platformFee,
            'platform_fee_rate' => $platformFeeRate,
            'total' => Money::fromMinor($totalMinor),

            // Do not expose seller account numbers here.
            'available_payment_methods' => $storePaymentMethods,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Final Preview Response
    |--------------------------------------------------------------------------
    */

    return [
        'fulfillment_type' => $fulfillmentType->value,

        'store_count' => count($stores),

        'subtotal' => Money::fromMinor(
            $grandSubtotalMinor
        ),

        'discount' => Money::fromMinor(
            $grandDiscountMinor
        ),

        'delivery_fee' => Money::fromMinor(
            $grandDeliveryMinor
        ),

        'platform_fee' => Money::fromMinor(
            $grandPlatformFeeMinor
        ),

        'grand_total' => Money::fromMinor(
            $grandTotalMinor
        ),

        'available_payment_methods' => $availablePaymentMethods,

        'default_payment_method' => 'cash',

        'stores' => $stores,
    ];
}


}