<?php

namespace App\Services;

use App\Enums\CheckoutBatchStatus;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Models\CheckoutBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CheckoutService
{
    public function __construct(
        protected CommissionService $commissionService,
        protected DeliveryFeeService $deliveryFeeService,
        protected DiscountService $discountService,
        protected OrderNotificationService $orderNotificationService,
        protected StoreSubscriptionService $subscriptionService,
    ) {}

    
public function checkout(

    array $data,

    ?int $buyerId = null

): CheckoutBatch {

    if (

        ! isset($data['items']) ||

        ! is_array($data['items']) ||

        count($data['items']) === 0

    ) {

        abort(422, 'Checkout must contain at least one item.');

    }



    if (

        ! isset($data['idempotency_key']) ||

        ! is_string($data['idempotency_key']) ||

        trim($data['idempotency_key']) === ''

    ) {

        abort(422, 'A valid idempotency key is required.');

    }



    foreach ($data['items'] as $item) {

        if (

            ! is_array($item) ||

            ! isset($item['product_id'], $item['quantity']) ||

            filter_var(

                $item['product_id'],

                FILTER_VALIDATE_INT

            ) === false ||

            filter_var(

                $item['quantity'],

                FILTER_VALIDATE_INT

            ) === false ||

            (int) $item['product_id'] <= 0 ||

            (int) $item['quantity'] <= 0

        ) {

            abort(422, 'Invalid checkout item.');

        }

    }



    $fulfillmentType = $data['fulfillment_type'];



    if (! $fulfillmentType instanceof FulfillmentType) {

        $fulfillmentType = FulfillmentType::from(

            $fulfillmentType

        );

    }



    /*

    |--------------------------------------------------------------------------

    | Canonical Request Fingerprint

    |--------------------------------------------------------------------------

    */



    $items = collect($data['items'])

        ->groupBy(

            fn (array $item) => (int) $item['product_id']

        )

        ->map(function ($group, $productId) {

            return [

                'product_id' => (int) $productId,

                'quantity' => $group->sum(

                    fn (array $item) => (int) $item['quantity']

                ),

            ];

        })

        ->sortBy('product_id')

        ->values()

        ->all();



    $fingerprint = hash(

        'sha256',

        json_encode([

            'buyer_id' => $buyerId,

            'buyer_name' => $data['buyer_name'],

            'buyer_email' => $data['buyer_email'] ?? null,

            'buyer_phone' => $data['buyer_phone'],

            'buyer_house_number' => $data['buyer_house_number'],

            'fulfillment_type' => $fulfillmentType->value,

            'payment_method' => $data['payment_method'] ?? null,

            'items' => $items,

        ], JSON_THROW_ON_ERROR)

    );



    $createdOrders = [];



    /*

    |--------------------------------------------------------------------------

    | Reusable Idempotency Verification

    |--------------------------------------------------------------------------

    */



    $verifyExisting = function (

        CheckoutBatch $existingBatch

    ) use ($buyerId, $fingerprint): CheckoutBatch {

        if (

            $buyerId === null ||

            $existingBatch->buyer_id === null ||

            (int) $existingBatch->buyer_id !== $buyerId

        ) {

            abort(

                409,

                'This checkout key has already been used.'

            );

        }



        $previousFingerprint = $existingBatch

            ->metadata['request_fingerprint'] ?? null;



        if (

            ! is_string($previousFingerprint) ||

            ! hash_equals(

                $previousFingerprint,

                $fingerprint

            )

        ) {

            abort(

                409,

                'This checkout key was used with a different request.'

            );

        }



        return $existingBatch->load([

            'orders.items',

            'orders.store',

        ]);

    };



    /*

    |--------------------------------------------------------------------------

    | Transactional Checkout

    |--------------------------------------------------------------------------

    */



    try {

        $batch = DB::transaction(function () use (

            $data,

            $buyerId,

            $fingerprint,

            $fulfillmentType,

            $verifyExisting,

            &$createdOrders

        ) {

            // Reset if Laravel retries after a deadlock.

            $createdOrders = [];



            $existingBatch = CheckoutBatch::query()

                ->where(

                    'idempotency_key',

                    $data['idempotency_key']

                )

                ->first();



            if ($existingBatch) {

                return $verifyExisting($existingBatch);

            }



            /*

            |--------------------------------------------------------------------------

            | Aggregate Quantities

            |--------------------------------------------------------------------------

            */



            $requestedQuantities = collect($data['items'])

                ->groupBy('product_id')

                ->map(fn ($items) => $items->sum(

                    fn ($item) => (int) $item['quantity']

                ));



            $productIds = $requestedQuantities

                ->keys()

                ->values();



            /*

            |--------------------------------------------------------------------------

            | Lock Products In Stable Order

            |--------------------------------------------------------------------------

            */



            $products = Product::query()

                ->with('store')

                ->whereIn('id', $productIds)

                ->orderBy('id')

                ->lockForUpdate()

                ->get()

                ->keyBy('id');

            /*
            |------------------------------------------------------------------
            | Recheck Idempotency After Product Locks
            |------------------------------------------------------------------
            |
            | A concurrent request may have committed while this request
            | waited for the product locks. Use a locking (current) read
            | to see that committed batch even under MySQL REPEATABLE READ.
            | Verify the buyer and request fingerprint before checking stock.
            |
            */

            $committedBatch = CheckoutBatch::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($committedBatch) {
                return $verifyExisting($committedBatch);
            }




            foreach ($requestedQuantities as $productId => $quantity) {

                $product = $products->get($productId);



                if (! $product) {

                    abort(

                        422,

                        "Product {$productId} not found."

                    );

                }



                if ($quantity <= 0) {

                    abort(

                        422,

                        "Invalid quantity for product {$productId}."

                    );

                }



                if (! $product->is_available) {

                    abort(

                        422,

                        "Product '{$product->title}' is unavailable."

                    );

                }



                // NULL represents unlimited stock.

                if (

                    $product->stock_quantity !== null &&

                    (int) $product->stock_quantity < $quantity

                ) {

                    abort(

                        422,

                        "Insufficient stock for '{$product->title}'."

                    );

                }



                if (! $product->store) {

                    abort(

                        422,

                        "Store for product '{$product->title}' was not found."

                    );

                }



                if (! $product->store->is_active) {

                    abort(

                        422,

                        "Store '{$product->store->store_name}' is inactive."

                    );

                }



                if (! $product->store->isOpen()) {

                    abort(

                        422,

                        "Store '{$product->store->store_name}' is closed."

                    );

                }

            }



            /*

            |--------------------------------------------------------------------------

            | Group Cart By Store

            |--------------------------------------------------------------------------

            */



            $itemsByStore = collect($data['items'])

                ->groupBy(

                    fn (array $item) =>

                        $products->get(

                            $item['product_id']

                        )->store_id

                );



            /*

            |--------------------------------------------------------------------------

            | Create Checkout Batch

            |--------------------------------------------------------------------------

            */



            $batch = CheckoutBatch::create([

                'batch_number' => $this->generateBatchNumber(),

                'buyer_id' => $buyerId,

                'buyer_name' => $data['buyer_name'],

                'buyer_email' => $data['buyer_email'] ?? null,

                'buyer_phone' => $data['buyer_phone'],

                'buyer_house_number' =>

                    $data['buyer_house_number'],

                'grand_total' => '0.00',

                'store_count' => $itemsByStore->count(),

                'order_count' => 0,

                'status' => CheckoutBatchStatus::PENDING,

                'payment_status' => PaymentStatus::UNPAID,

                'payment_method' => $data['payment_method'] ?? null,

                'idempotency_key' => $data['idempotency_key'],

                'metadata' => [

                    'request_fingerprint' => $fingerprint,

                ],

            ]);



            $orderCount = 0;

            $grandTotalMinor = 0;



            /*

            |--------------------------------------------------------------------------

            | Create Orders Per Store

            |--------------------------------------------------------------------------

            */



            foreach ($itemsByStore as $storeId => $storeItems) {

                $order = $this->createStoreOrder(

                    batch: $batch,

                    storeId: (int) $storeId,

                    cartItems: $storeItems->values()->all(),

                    products: $products,

                    data: $data,

                    fulfillmentType: $fulfillmentType,

                );



                $createdOrders[] = $order;



                $grandTotalMinor += Money::toMinor(

                    $order->total_amount

                );



                $orderCount++;

            }



            $batch->update([

                'grand_total' => Money::fromMinor(

                    $grandTotalMinor

                ),

                'order_count' => $orderCount,

                'status' => CheckoutBatchStatus::COMPLETED,

            ]);



            return $batch->load([

                'orders.items',

                'orders.store',

            ]);

        }, 3);

    } catch (\Illuminate\Database\QueryException $exception) {

        /*

        |--------------------------------------------------------------------------

        | Concurrent Idempotency Recovery

        |--------------------------------------------------------------------------

        |

        | If another request inserted the same unique key

        | while this transaction was running, retrieve and

        | verify its already-committed result.

        |

        */



        $sqlState = (string) $exception->getCode();



        $message = strtolower(

            $exception->getMessage()

        );



        $isIdempotencyCollision =

            in_array($sqlState, ['23000', '23505'], true)

            && str_contains($message, 'idempotency_key');



        if (! $isIdempotencyCollision) {

            throw $exception;

        }



        $existingBatch = CheckoutBatch::query()

            ->where(

                'idempotency_key',

                $data['idempotency_key']

            )

            ->first();



        if (! $existingBatch) {

            throw $exception;

        }



        // No orders from the rolled-back attempt should broadcast.

        $createdOrders = [];



        $batch = $verifyExisting($existingBatch);

    }



    /*

    |--------------------------------------------------------------------------

    | Broadcast Newly Created Orders Only

    |--------------------------------------------------------------------------

    */



    foreach ($createdOrders as $order) {

        try {

            $order->loadMissing('store');



            OrderCreated::dispatch($order);

        } catch (Throwable $exception) {

            Log::warning(

                'Order created broadcast failed.',

                [

                    'order_id' => $order->id,

                    'error' => $exception->getMessage(),

                ]

            );

        }

    }



    return $batch;

}



private function createStoreOrder(
    CheckoutBatch $batch,
    int $storeId,
    array $cartItems,
    $products,
    array $data,
    FulfillmentType $fulfillmentType,
): Order {
    $subtotalMinor = 0;

    foreach ($cartItems as $cartItem) {
        $product = $products->get($cartItem['product_id']);

        $subtotalMinor += Money::multiply(
            $product->price,
            (int) $cartItem['quantity']
        );
    }

    $subtotal = Money::fromMinor($subtotalMinor);

    $discountAmount = $this->discountService->calculate(
        cartItems: $cartItems,
        storeId: $storeId
    );

    $deliveryFee = $this->deliveryFeeService->calculate(
        fulfillmentType: $fulfillmentType,
        storeId: $storeId
    );

    $store = $products
        ->get($cartItems[0]['product_id'])
        ->store;

    /*
    |--------------------------------------------------------------------------
    | Validate Seller Payment Method
    |--------------------------------------------------------------------------
    */

    $paymentMethod = strtolower(
        trim((string) ($data['payment_method'] ?? ''))
    );

    $digitalMethods = [
        'easypaisa',
        'jazzcash',
        'bank_transfer',
    ];

    $paymentSnapshot = null;

    if (in_array($paymentMethod, $digitalMethods, true)) {
        $methods = $store->payment_methods ?? [];
        $account = $methods[$paymentMethod] ?? null;

        abort_unless(
            is_array($account)
                && ($account['enabled'] ?? false) === true,
            422,
            'Selected payment method is unavailable for this store.'
        );

        $paymentSnapshot = [
            'method' => $paymentMethod,
            'account_title' => $account['account_title'] ?? null,
            'account_number' => $account['account_number'] ?? null,
            'bank_name' => $account['bank_name'] ?? null,
            'iban' => $account['iban'] ?? null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Platform Commission
    |--------------------------------------------------------------------------
    |
    | Launch: platform commission disabled.
    | Future: enable through marketplace config.
    | Existing commission calculation remains preserved.
    |
    */

    $platformFee = '0.00';
    $platformFeeRate = '0.0000';

    if (config('marketplace.platform_commission_enabled', false)) {
        $subscription = $this->subscriptionService->active($store);

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
    | Calculate Final Total
    |--------------------------------------------------------------------------
    */

    $totalMinor = Money::toMinor($subtotal)
        - Money::toMinor($discountAmount)
        + Money::toMinor($deliveryFee)
        + Money::toMinor($platformFee);

    if ($totalMinor < 0) {
        throw new RuntimeException(
            'Order total cannot be negative.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Order
    |--------------------------------------------------------------------------
    */

    $order = Order::create([
        'checkout_batch_id' => $batch->id,
        'order_number' => $this->generateOrderNumber(
            $batch,
            $storeId
        ),
        'access_token' => Str::random(64),
        'buyer_id' => $batch->buyer_id,
        'store_id' => $storeId,
        'buyer_name' => $batch->buyer_name,
        'buyer_email' => $batch->buyer_email,
        'buyer_house_number' => $batch->buyer_house_number,
        'buyer_phone' => $batch->buyer_phone,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'delivery_fee' => $deliveryFee,
        'platform_fee' => $platformFee,
        'platform_fee_rate' => $platformFeeRate,
        'total_amount' => Money::fromMinor($totalMinor),
        'fulfillment_type' => $fulfillmentType,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'payment_method' => $paymentMethod,
        'metadata' => $paymentSnapshot !== null
            ? ['payment_instructions' => $paymentSnapshot]
            : [],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Create Order Items + Deduct Stock
    |--------------------------------------------------------------------------
    */

    foreach ($cartItems as $cartItem) {
        $product = $products->get($cartItem['product_id']);

        $quantity = (int) $cartItem['quantity'];
        $unitPrice = (string) $product->price;

        $stockDeducted = $product->stock_quantity !== null;

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => Money::fromMinor(
                Money::multiply($unitPrice, $quantity)
            ),
            'platform_fee' => '0.00',
            'platform_fee_rate' => '0.0000',
            'stock_deducted' => $stockDeducted,
        ]);

        if ($stockDeducted) {
            $product->decrement(
                'stock_quantity',
                $quantity
            );
        }
    }

    // Save notifications atomically with checkout.
    $this->orderNotificationService->created($order);

    return $order;
}


    private function generateBatchNumber(): string
    {
        return 'B-'
            .now()->format('YmdHisv')
            .'-'
            .strtoupper(Str::random(6));
    }

    private function generateOrderNumber(
        CheckoutBatch $batch,
        int $storeId
    ): string {
        return 'ORD-'.$batch->id.'-'.$storeId;
    }
}
