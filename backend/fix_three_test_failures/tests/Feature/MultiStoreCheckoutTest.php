<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CheckoutBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an authenticated buyer.
    $this->buyer = User::create([
        'name' => 'Test Buyer',
        'email' => 'buyer@example.test',
        'phone' => '03001111111',
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
        'house_number' => 'House 10',
    ]);

    Sanctum::actingAs($this->buyer);

    // Create a product category.
    $category = Category::create([
        'name' => 'Groceries',
        'slug' => 'groceries',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Create two independent sellers.
    $sellerA = User::create([
        'name' => 'Seller A',
        'email' => 'seller-a@example.test',
        'phone' => '03002222222',
        'password' => 'Password123!',
        'role' => UserRole::SELLER,
        'is_active' => true,
    ]);

    $sellerB = User::create([
        'name' => 'Seller B',
        'email' => 'seller-b@example.test',
        'phone' => '03003333333',
        'password' => 'Password123!',
        'role' => UserRole::SELLER,
        'is_active' => true,
    ]);

    // Create two active, open stores.
    $this->storeA = Store::create([
        'user_id' => $sellerA->id,
        'store_name' => 'Test Store A',
        'slug' => 'test-store-a',
        'house_number' => 'Shop 1',
        'is_open' => true,
        'is_active' => true,
    ]);

    $this->storeB = Store::create([
        'user_id' => $sellerB->id,
        'store_name' => 'Test Store B',
        'slug' => 'test-store-b',
        'house_number' => 'Shop 2',
        'is_open' => true,
        'is_active' => true,
    ]);

    // Create stock-tracked products.
    $this->productA = Product::create([
        'store_id' => $this->storeA->id,
        'category_id' => $category->id,
        'title' => 'Milk',
        'price' => '300.00',
        'is_available' => true,
        'stock_quantity' => 10,
        'sales_count' => 0,
        'sort_order' => 1,
    ]);

    $this->productB = Product::create([
        'store_id' => $this->storeB->id,
        'category_id' => $category->id,
        'title' => 'Bread',
        'price' => '200.00',
        'is_available' => true,
        'stock_quantity' => 5,
        'sales_count' => 0,
        'sort_order' => 1,
    ]);

    // Prepare a fresh checkout request.
    $this->checkoutData = [
        'buyer_name' => 'Test Buyer',
        'buyer_email' => 'buyer@example.test',
        'buyer_phone' => '03001111111',
        'buyer_house_number' => 'House 10',
        'fulfillment_type' => 'seller_delivery',
        'payment_method' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            [
                'product_id' => $this->productA->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $this->productB->id,
                'quantity' => 1,
            ],
        ],
    ];
});

/*
|--------------------------------------------------------------------------
| Test 1: Multi-store checkout creates separate orders
|--------------------------------------------------------------------------
*/


it('creates one checkout batch with separate store orders', function () {
    // Show the original exception instead of HTTP 500.
    $this->withoutExceptionHandling();

    $response = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.store_count', 2)
        ->assertJsonPath('data.order_count', 2)
        ->assertJsonCount(2, 'data.orders')
        ->assertJsonCount(2, 'whatsapp_receipts');

    expect(CheckoutBatch::count())->toBe(1);
    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(2);

    $batch = CheckoutBatch::firstOrFail();

    expect(
        $batch->orders()->distinct('store_id')->count('store_id')
    )->toBe(2);

    $this->assertDatabaseHas('orders', [
        'checkout_batch_id' => $batch->id,
        'store_id' => $this->storeA->id,
        'total_amount' => '600.00',
        'payment_status' => 'unpaid',
    ]);

    $this->assertDatabaseHas('orders', [
        'checkout_batch_id' => $batch->id,
        'store_id' => $this->storeB->id,
        'total_amount' => '200.00',
        'payment_status' => 'unpaid',
    ]);

    expect((string) $batch->grand_total)->toBe('800.00');
});


/*
|--------------------------------------------------------------------------
| Test 2: Checkout deducts inventory correctly
|--------------------------------------------------------------------------
*/

it('deducts stock after successful checkout', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    expect($this->productA->fresh()->stock_quantity)->toBe(8);
    expect($this->productB->fresh()->stock_quantity)->toBe(4);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $this->productA->id,
        'quantity' => 2,
        'stock_deducted' => 1,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $this->productB->id,
        'quantity' => 1,
        'stock_deducted' => 1,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 3: Idempotent retry does not create duplicate orders
|--------------------------------------------------------------------------
*/

it('does not duplicate orders or stock deductions on retry', function () {
    $first = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $first->assertCreated();

    $second = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $second
        ->assertCreated()
        ->assertJsonPath(
            'data.batch_number',
            $first->json('data.batch_number')
        );

    expect(CheckoutBatch::count())->toBe(1);
    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(2);

    expect($this->productA->fresh()->stock_quantity)->toBe(8);
    expect($this->productB->fresh()->stock_quantity)->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Test 4: Same idempotency key with different cart is rejected
|--------------------------------------------------------------------------
*/

it('rejects reusing an idempotency key with different items', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $changedData = $this->checkoutData;

    $changedData['items'][0]['quantity'] = 3;

    $this->postJson(
        '/api/v1/checkout',
        $changedData
    )->assertStatus(409);

    expect(CheckoutBatch::count())->toBe(1);
    expect(Order::count())->toBe(2);

    expect($this->productA->fresh()->stock_quantity)->toBe(8);
    expect($this->productB->fresh()->stock_quantity)->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Test 5: Insufficient stock rolls back the entire checkout
|--------------------------------------------------------------------------
*/

it('rejects insufficient stock without creating a partial order', function () {
    $this->productB->update([
        'stock_quantity' => 0,
    ]);

    $response = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $response->assertStatus(422);

    expect(CheckoutBatch::count())->toBe(0);
    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);

    expect($this->productA->fresh()->stock_quantity)->toBe(10);
    expect($this->productB->fresh()->stock_quantity)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 6: Unsupported seller payment method is rejected
|--------------------------------------------------------------------------
*/

it('rejects digital payment when a seller has not enabled it', function () {
    $data = $this->checkoutData;
    $data['payment_method'] = 'easypaisa';

    $response = $this->postJson(
        '/api/v1/checkout',
        $data
    );

    $response->assertStatus(422);

    expect(CheckoutBatch::count())->toBe(0);
    expect(Order::count())->toBe(0);

    expect($this->productA->fresh()->stock_quantity)->toBe(10);
    expect($this->productB->fresh()->stock_quantity)->toBe(5);
});


it('creates multi-store orders with correct Easypaisa payment snapshots', function () {
    $this->storeA->update([
        'payment_methods' => [
            'easypaisa' => [
                'enabled' => true,
                'account_title' => 'Seller A',
                'account_number' => '03002222222',
            ],
        ],
    ]);

    $this->storeB->update([
        'payment_methods' => [
            'easypaisa' => [
                'enabled' => true,
                'account_title' => 'Seller B',
                'account_number' => '03003333333',
            ],
        ],
    ]);

    $data = $this->checkoutData;
    $data['payment_method'] = 'easypaisa';

    $response = $this->postJson(
        '/api/v1/checkout',
        $data
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.order_count', 2);

    expect(Order::count())->toBe(2);

    $orderA = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $orderB = Order::where(
        'store_id',
        $this->storeB->id
    )->firstOrFail();

    expect($orderA->payment_method)->toBe('easypaisa');
    expect($orderB->payment_method)->toBe('easypaisa');

    expect(
        $orderA->metadata['payment_instructions']['account_number']
    )->toBe('03002222222');

    expect(
        $orderB->metadata['payment_instructions']['account_number']
    )->toBe('03003333333');

    $this->assertDatabaseHas('orders', [
        'id' => $orderA->id,
        'payment_status' => 'unpaid',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orderB->id,
        'payment_status' => 'unpaid',
    ]);

    // Changing a seller account must not change
    // an already created order's payment instructions.
    $this->storeA->update([
        'payment_methods' => [
            'easypaisa' => [
                'enabled' => true,
                'account_title' => 'Seller A Updated',
                'account_number' => '03009999999',
            ],
        ],
    ]);

    expect(
        $orderA->fresh()->metadata['payment_instructions']['account_number']
    )->toBe('03002222222');
});



it('creates separate buyer and seller inbox notifications for each store order', function () {
    $response = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $response->assertCreated();

    $orders = Order::with('store')->get();

    expect($orders)->toHaveCount(2);

    foreach ($orders as $order) {
        $sellerId = $order->store->user_id;

        // Seller receives notification for their own order.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $sellerId,
            'type' => 'order_created',
            'deduplication_key' =>
                "user:{$sellerId}:order:{$order->id}:seller:created",
        ]);

        // Buyer receives a separate notification per order.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->buyer->id,
            'type' => 'order_created',
            'deduplication_key' =>
                "user:{$this->buyer->id}:order:{$order->id}:buyer:created",
        ]);
    }

    // Two sellers + two buyer notifications.
    expect(
        \App\Models\UserNotification::query()
            ->where('type', 'order_created')
            ->count()
    )->toBe(4);

    // Retrying checkout must not create duplicate notifications.
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    expect(
        \App\Models\UserNotification::query()
            ->where('type', 'order_created')
            ->count()
    )->toBe(4);
});


it('synchronizes batch payment after both sellers confirm payment', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $orderA = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $orderB = Order::where(
        'store_id',
        $this->storeB->id
    )->firstOrFail();

    $sellerA = $this->storeA->owner;
    $sellerB = $this->storeB->owner;

    $statusService = app(
        \App\Services\OrderStatusService::class
    );

    $paymentService = app(
        \App\Services\SellerPaymentService::class
    );

    // Cash payments require the order to be accepted.
    $statusService->update(
        $orderA,
        $sellerA,
        \App\Enums\OrderStatus::ACCEPTED
    );

    $statusService->update(
        $orderB,
        $sellerB,
        \App\Enums\OrderStatus::ACCEPTED
    );

    $paymentService->confirm(
        $orderA,
        $sellerA,
        'CASH-A-001'
    );

    expect($orderA->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    expect($orderB->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::UNPAID);

    expect($orderA->checkoutBatch->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PENDING);

    $paymentService->confirm(
        $orderB,
        $sellerB,
        'CASH-B-001'
    );

    expect($orderB->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    $batch = $orderA->checkoutBatch->fresh();

    expect($batch->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    expect($batch->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('orders', [
        'id' => $orderA->id,
        'payment_reference' => 'CASH-A-001',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orderB->id,
        'payment_reference' => 'CASH-B-001',
    ]);
});


it('prevents a seller from confirming another stores payment', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $orderB = Order::where(
        'store_id',
        $this->storeB->id
    )->firstOrFail();

    $sellerA = $this->storeA->owner;

    try {
        app(\App\Services\SellerPaymentService::class)
            ->confirm(
                $orderB,
                $sellerA,
                'UNAUTHORIZED-001'
            );

        $this->fail(
            'Another seller should not be allowed to confirm this payment.'
        );
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    expect($orderB->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::UNPAID);

    expect($orderB->fresh()->paid_at)->toBeNull();
});


it('does not duplicate payment notifications on repeated confirmation', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $seller = $this->storeA->owner;

    app(\App\Services\OrderStatusService::class)->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::ACCEPTED
    );

    $service = app(
        \App\Services\SellerPaymentService::class
    );

    $service->confirm(
        $order,
        $seller,
        'FIRST-REFERENCE'
    );

    $firstNotificationCount =
        \App\Models\UserNotification::query()
            ->where('type', 'order_payment_confirmed')
            ->where('user_id', $this->buyer->id)
            ->count();

    expect($firstNotificationCount)->toBe(1);

    $service->confirm(
        $order,
        $seller,
        'SECOND-REFERENCE'
    );

    $secondNotificationCount =
        \App\Models\UserNotification::query()
            ->where('type', 'order_payment_confirmed')
            ->where('user_id', $this->buyer->id)
            ->count();

    expect($secondNotificationCount)->toBe(1);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'payment_reference' => 'FIRST-REFERENCE',
        'payment_status' => 'paid',
    ]);
});


it('restores stock once when an unpaid order is cancelled', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $seller = $this->storeA->owner;

    // Checkout deducted 2 units from initial stock 10.
    expect($this->productA->fresh()->stock_quantity)
        ->toBe(8);

    $service = app(
        \App\Services\OrderStatusService::class
    );

    $service->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::CANCELLED,
        'Buyer requested cancellation.'
    );

    expect($order->fresh()->status)
        ->toBe(\App\Enums\OrderStatus::CANCELLED);

    expect($this->productA->fresh()->stock_quantity)
        ->toBe(10);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $this->productA->id,
        'stock_deducted' => 0,
    ]);

    // Repeating cancellation must not restore stock twice.
    $service->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::CANCELLED
    );

    expect($this->productA->fresh()->stock_quantity)
        ->toBe(10);

    // Other store's order remains unaffected.
    expect($this->productB->fresh()->stock_quantity)
        ->toBe(4);
});


it('prevents cancelling an already paid order', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $seller = $this->storeA->owner;

    $statusService = app(
        \App\Services\OrderStatusService::class
    );

    $statusService->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::ACCEPTED
    );

    app(\App\Services\SellerPaymentService::class)
        ->confirm(
            $order,
            $seller,
            'PAID-001'
        );

    try {
        $statusService->update(
            $order,
            $seller,
            \App\Enums\OrderStatus::CANCELLED
        );

        $this->fail(
            'Paid orders must not be cancelled automatically.'
        );
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }

    expect($order->fresh()->status)
        ->toBe(\App\Enums\OrderStatus::ACCEPTED);

    expect($order->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    expect($this->productA->fresh()->stock_quantity)
        ->toBe(8);
});


it('increments sales count only once when an order is delivered', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $seller = $this->storeA->owner;

    $service = app(
        \App\Services\OrderStatusService::class
    );

    // Initially the product has no completed sales.
    expect((int) $this->productA->fresh()->sales_count)
        ->toBe(0);

    // Seller accepts the order.
    $service->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::ACCEPTED
    );

    // Seller delivers the order.
    $service->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::DELIVERED
    );

    expect($order->fresh()->status)
        ->toBe(\App\Enums\OrderStatus::DELIVERED);

    // Buyer ordered two units of product A.
    expect((int) $this->productA->fresh()->sales_count)
        ->toBe(2);

    // Delivery must not deduct stock again.
    expect((int) $this->productA->fresh()->stock_quantity)
        ->toBe(8);

    // Repeating the same status must be idempotent.
    $service->update(
        $order,
        $seller,
        \App\Enums\OrderStatus::DELIVERED
    );

    expect((int) $this->productA->fresh()->sales_count)
        ->toBe(2);

    // Another seller's inventory remains unaffected.
    expect((int) $this->productB->fresh()->sales_count)
        ->toBe(0);
});



it('confirms payment on delivery and synchronizes the checkout batch', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $orderA = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    $orderB = Order::where(
        'store_id',
        $this->storeB->id
    )->firstOrFail();

    $sellerA = $this->storeA->owner;
    $sellerB = $this->storeB->owner;

    $service = app(
        \App\Services\OrderStatusService::class
    );

    $service->update(
        $orderA,
        $sellerA,
        \App\Enums\OrderStatus::ACCEPTED
    );

    $service->update(
        $orderB,
        $sellerB,
        \App\Enums\OrderStatus::ACCEPTED
    );

    // First seller delivers and receives cash.
    $service->update(
        order: $orderA,
        seller: $sellerA,
        newStatus: \App\Enums\OrderStatus::DELIVERED,
        paymentReceived: true,
        paymentReference: 'DELIVERY-A-001'
    );

    $orderA->refresh();

    expect($orderA->status)
        ->toBe(\App\Enums\OrderStatus::DELIVERED);

    expect($orderA->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    expect($orderA->payment_reference)
        ->toBe('DELIVERY-A-001');

    expect($orderA->paid_at)->not->toBeNull();

    // Store B still has an unpaid order.
    expect($orderB->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::UNPAID);

    expect($orderA->checkoutBatch->fresh()->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PENDING);

    // Second seller delivers and receives cash.
    $service->update(
        order: $orderB,
        seller: $sellerB,
        newStatus: \App\Enums\OrderStatus::DELIVERED,
        paymentReceived: true,
        paymentReference: 'DELIVERY-B-001'
    );

    $batch = $orderA->checkoutBatch->fresh();

    expect($batch->payment_status)
        ->toBe(\App\Enums\PaymentStatus::PAID);

    expect($batch->paid_at)->not->toBeNull();

    // Buyer receives exactly two payment confirmations.
    $notificationCount =
        \App\Models\UserNotification::query()
            ->where('user_id', $this->buyer->id)
            ->where('type', 'order_payment_confirmed')
            ->count();

    expect($notificationCount)->toBe(2);

    // Repeating delivery + payment must not create duplicates.
    $service->update(
        order: $orderA,
        seller: $sellerA,
        newStatus: \App\Enums\OrderStatus::DELIVERED,
        paymentReceived: true,
        paymentReference: 'DUPLICATE'
    );

    expect(
        \App\Models\UserNotification::query()
            ->where('user_id', $this->buyer->id)
            ->where('type', 'order_payment_confirmed')
            ->count()
    )->toBe(2);

    expect($orderA->fresh()->payment_reference)
        ->toBe('DELIVERY-A-001');

    // Sales count should reflect delivered quantities.
    expect((int) $this->productA->fresh()->sales_count)
        ->toBe(2);

    expect((int) $this->productB->fresh()->sales_count)
        ->toBe(1);
});



it('rolls back all stores when one seller does not accept digital payment', function () {
    $this->storeA->update([
        'payment_methods' => [
            'easypaisa' => [
                'enabled' => true,
                'account_title' => 'Seller A',
                'account_number' => '03002222222',
            ],
        ],
    ]);

    // Store B has not enabled Easypaisa.
    $data = $this->checkoutData;
    $data['payment_method'] = 'easypaisa';

    $response = $this->postJson(
        '/api/v1/checkout',
        $data
    );

    $response->assertStatus(422);

    expect(CheckoutBatch::count())->toBe(0);
    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);

    expect((int) $this->productA->fresh()->stock_quantity)
        ->toBe(10);

    expect((int) $this->productB->fresh()->stock_quantity)
        ->toBe(5);

    expect(
        \App\Models\UserNotification::count()
    )->toBe(0);
});



it('rejects changing payment method on an existing checkout key', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $changedData = $this->checkoutData;
    $changedData['payment_method'] = 'easypaisa';

    $response = $this->postJson(
        '/api/v1/checkout',
        $changedData
    );

    $response->assertStatus(409);

    expect(CheckoutBatch::count())->toBe(1);
    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(2);

    expect((int) $this->productA->fresh()->stock_quantity)
        ->toBe(8);

    expect((int) $this->productB->fresh()->stock_quantity)
        ->toBe(4);
});




it('prevents sellers from changing another stores order status', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $orderB = Order::query()
        ->where('store_id', $this->storeB->id)
        ->firstOrFail();

    $sellerA = $this->storeA->owner;

    try {
        app(\App\Services\OrderStatusService::class)
            ->update(
                order: $orderB,
                seller: $sellerA,
                newStatus: \App\Enums\OrderStatus::ACCEPTED
            );

        $this->fail(
            'Seller A must not update Store B orders.'
        );
    } catch (
        \Symfony\Component\HttpKernel\Exception\HttpException $exception
    ) {
        expect($exception->getStatusCode())->toBe(403);
    }

    expect($orderB->fresh()->status)
        ->toBe(\App\Enums\OrderStatus::PENDING);

    expect((int) $this->productB->fresh()->stock_quantity)
        ->toBe(4);
});



it('allows guests to track their own orders using valid tokens', function () {
    // Remove the buyer authenticated in beforeEach().
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    $response = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $response
        ->assertCreated()
        ->assertJsonCount(2, 'guest_tracking');

    $tracking = $response->json('guest_tracking');

    expect($tracking[0]['access_token'])
        ->toHaveLength(64);

    $this->getJson(
        '/api/v1/orders/'
        . $tracking[0]['order_number']
        . '?access_token='
        . $tracking[0]['access_token']
    )
        ->assertOk()
        ->assertJsonPath(
            'data.order_number',
            $tracking[0]['order_number']
        );

    expect(Order::whereNull('buyer_id')->count())
        ->toBe(2);
});

it('rejects invalid and cross-order guest tracking tokens', function () {
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    $checkout = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $checkout->assertCreated();

    $tracking = $checkout->json('guest_tracking');

    $orderA = $tracking[0];
    $orderB = $tracking[1];

    // Wrong token.
    $this->getJson(
        '/api/v1/orders/'
        . $orderA['order_number']
        . '?access_token='
        . str_repeat('x', 64)
    )->assertForbidden();

    // Store B token cannot open Store A order.
    $this->getJson(
        '/api/v1/orders/'
        . $orderA['order_number']
        . '?access_token='
        . $orderB['access_token']
    )->assertForbidden();

    // Missing token.
    $this->getJson(
        '/api/v1/orders/' . $orderA['order_number']
    )->assertForbidden();
});

it('limits guest batch tracking to one authorized order', function () {
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    $checkout = $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    );

    $checkout->assertCreated();

    $tracking = $checkout->json('guest_tracking');

    $batch = CheckoutBatch::firstOrFail();

    $response = $this->getJson(
        '/api/v1/checkout-batches/'
        . $batch->batch_number
        . '?access_token='
        . $tracking[0]['access_token']
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.order_number',
            $tracking[0]['order_number']
        )
        ->assertJsonMissingPath('data.orders');

    $this->getJson(
        '/api/v1/checkout-batches/'
        . $batch->batch_number
        . '?access_token='
        . str_repeat('x', 64)
    )->assertForbidden();
});

it('prevents guest token access to registered buyer orders', function () {
    // First create an authenticated buyer checkout.
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where(
        'store_id',
        $this->storeA->id
    )->firstOrFail();

    expect($order->buyer_id)->toBe($this->buyer->id);

    // Switch to anonymous visitor.
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    $this->getJson(
        '/api/v1/orders/'
        . $order->order_number
        . '?access_token='
        . $order->access_token
    )->assertForbidden();
});



it('allows a registered buyer to view only their own order', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where('store_id', $this->storeA->id)
        ->firstOrFail();

    $this->getJson(
        '/api/v1/orders/' . $order->order_number
    )
        ->assertOk()
        ->assertJsonPath(
            'data.order_number',
            $order->order_number
        );
});

it('prevents another registered buyer from viewing private orders', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $order = Order::where('store_id', $this->storeA->id)
        ->firstOrFail();

    $otherBuyer = User::create([
        'name' => 'Other Buyer',
        'email' => 'other-buyer@example.test',
        'phone' => '03004444444',
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
    ]);

    Sanctum::actingAs($otherBuyer);

    $this->getJson(
        '/api/v1/orders/' . $order->order_number
    )->assertForbidden();

    $this->getJson(
        '/api/v1/checkout-batches/'
        . $order->checkoutBatch->batch_number
    )->assertForbidden();
});

it('shows only the authenticated buyers orders in history', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $otherBuyer = User::create([
        'name' => 'Other Buyer',
        'email' => 'other-history@example.test',
        'phone' => '03005555555',
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
    ]);

    Sanctum::actingAs($otherBuyer);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    Sanctum::actingAs($this->buyer);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});



it('creates push and email outbox records for every store order', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $orders = Order::with('store')->get();

    expect($orders)->toHaveCount(2);

    foreach ($orders as $order) {
        $this->assertDatabaseHas('notification_outboxes', [
            'deduplication_key' =>
                "order:{$order->id}:seller:push:created",
        ]);

        $this->assertDatabaseHas('notification_outboxes', [
            'deduplication_key' =>
                "order:{$order->id}:buyer:push:created",
        ]);

        $this->assertDatabaseHas('notification_outboxes', [
            'deduplication_key' =>
                "order:{$order->id}:buyer:email:created",
        ]);
    }

    expect(
        \App\Models\NotificationOutbox::query()->count()
    )->toBe(6);
});


it('does not duplicate email or push outbox records on checkout retry', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $firstCount = \App\Models\NotificationOutbox::count();

    expect($firstCount)->toBe(6);

    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    expect(
        \App\Models\NotificationOutbox::count()
    )->toBe($firstCount);

    expect(CheckoutBatch::count())->toBe(1);
    expect(Order::count())->toBe(2);
});


it('processes a pending notification exactly once', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $notification = \App\Models\NotificationOutbox::query()
        ->where('status', 'pending')
        ->firstOrFail();

    $manager = \Mockery::mock(
        \App\Services\NotificationChannelManager::class
    );

    $manager->shouldReceive('send')
        ->once()
        ->with(
            \Mockery::on(
                fn ($outbox) =>
                    $outbox->id === $notification->id
            )
        );

    $job = new \App\Jobs\ProcessNotificationOutboxJob(
        $notification->id
    );

    $job->handle($manager);

    $this->assertDatabaseHas('notification_outboxes', [
        'id' => $notification->id,
        'status' => 'processed',
        'attempts' => 1,
    ]);

    expect(
        $notification->fresh()->processed_at
    )->not->toBeNull();

    // A duplicate job should not deliver it again.
    $job->handle($manager);

    expect(
        (int) $notification->fresh()->attempts
    )->toBe(1);
});




it('retries failed notification delivery using the outbox', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $notification = \App\Models\NotificationOutbox::query()
        ->where('status', 'pending')
        ->firstOrFail();

    $manager = \Mockery::mock(
        \App\Services\NotificationChannelManager::class
    );

    $manager->shouldReceive('send')
        ->once()
        ->andThrow(
            new \RuntimeException('Temporary delivery failure')
        );

    $job = new \App\Jobs\ProcessNotificationOutboxJob(
        $notification->id
    );

    // Current implementation catches delivery errors.
    $job->handle($manager);

    $notification->refresh();

    expect($notification->status->value)
        ->toBe('pending');

    expect((int) $notification->attempts)
        ->toBe(1);

    expect($notification->available_at)
        ->not->toBeNull();

    expect($notification->available_at->isFuture())
        ->toBeTrue();

    expect($notification->last_error)
        ->toContain('Temporary delivery failure');

    // The same job cannot retry before available_at.
    $job->handle($manager);

    expect(
        (int) $notification->fresh()->attempts
    )->toBe(1);
});




it('processes a notification successfully after a scheduled retry', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $notification = \App\Models\NotificationOutbox::query()
        ->where('status', 'pending')
        ->firstOrFail();

    $calls = 0;

    $manager = \Mockery::mock(
        \App\Services\NotificationChannelManager::class
    );

    $manager->shouldReceive('send')
        ->twice()
        ->andReturnUsing(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                throw new \RuntimeException(
                    'Temporary email provider failure'
                );
            }

            // Second attempt succeeds.
            return null;
        });

    $firstJob = new \App\Jobs\ProcessNotificationOutboxJob(
        $notification->id
    );

    $firstJob->handle($manager);

    $notification->refresh();

    expect($notification->status->value)
        ->toBe('pending');

    expect((int) $notification->attempts)
        ->toBe(1);

    expect($notification->available_at)
        ->not->toBeNull();

    // Simulate the retry delay having elapsed.
    \Illuminate\Support\Facades\DB::table(
        'notification_outboxes'
    )
        ->where('id', $notification->id)
        ->update([
            'available_at' => now()->subSeconds(5),
        ]);

    $secondJob = new \App\Jobs\ProcessNotificationOutboxJob(
        $notification->id
    );

    $secondJob->handle($manager);

    $notification->refresh();

    expect($calls)->toBe(2);

    if ($notification->status->value !== 'processed') {
        throw new \RuntimeException(
            'Retry did not complete. Status: '
            . $notification->status->value
            . '; Attempts: '
            . $notification->attempts
            . '; Last error: '
            . ($notification->last_error ?? 'none')
        );
    }

    expect($notification->status->value)
        ->toBe('processed');

    expect((int) $notification->attempts)
        ->toBe(2);

    expect($notification->processed_at)
        ->not->toBeNull();

    expect($notification->last_error)
        ->toBeNull();
});



it('passes the order receipt to Laravel Mail as HTML and plain text', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $notification = \App\Models\NotificationOutbox::query()
        ->where('channel', 'email')
        ->firstOrFail();

    $notification->load('notifiable');

    $payload = $notification->payload;

    // Professional receipts use Mail::send(), not Mail::raw().
    \Illuminate\Support\Facades\Mail::shouldReceive('send')
        ->once()
        ->with(
            [
                'html' => 'emails.order-notification',
                'text' => 'emails.order-notification-text',
            ],
            \Mockery::on(function ($data) use ($payload): bool {
                return is_array($data)
                    && ($data['notificationType'] ?? null) === 'order_created'
                    && ($data['body'] ?? null) === $payload['body']
                    && ($data['receipt']['order_number'] ?? null)
                        === ($payload['receipt']['order_number'] ?? null);
            }),
            \Mockery::type(\Closure::class)
        );

    app(
        \App\Notifications\Channels\EmailNotificationChannel::class
    )->send($notification);
});


it('dispatches due notification outbox jobs', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    \Illuminate\Support\Facades\Queue::fake();

    $pendingCount =
        \App\Models\NotificationOutbox::query()
            ->where('status', 'pending')
            ->count();

    expect($pendingCount)->toBe(6);

    $this->artisan('notifications:dispatch-pending')
        ->assertSuccessful();

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Jobs\ProcessNotificationOutboxJob::class,
        $pendingCount
    );
});


it('recovers notifications stuck in processing state', function () {
    $this->postJson(
        '/api/v1/checkout',
        $this->checkoutData
    )->assertCreated();

    $notification =
        \App\Models\NotificationOutbox::query()
            ->firstOrFail();

    // Simulate a worker that crashed 15 minutes ago.
    \Illuminate\Support\Facades\DB::table(
        'notification_outboxes'
    )
        ->where('id', $notification->id)
        ->update([
            'status' => 'processing',
            'attempts' => 1,
            'updated_at' => now()->subMinutes(15),
        ]);

    \Illuminate\Support\Facades\Queue::fake();

    $this->artisan('notifications:dispatch-pending')
        ->assertSuccessful();

    $notification->refresh();

    expect($notification->status->value)
        ->toBe('pending');

    expect((int) $notification->attempts)
        ->toBe(1);

    expect($notification->available_at)
        ->not->toBeNull();

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Jobs\ProcessNotificationOutboxJob::class,
        fn ($job) =>
            $job->notificationId === $notification->id
    );
});
