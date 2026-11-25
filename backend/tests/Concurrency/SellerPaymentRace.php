<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CheckoutBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CheckoutService;
use App\Services\OrderStatusService;
use App\Services\SellerPaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (
    ! app()->environment('testing')
    || DB::connection()->getDriverName() !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'neighbour_marketplace_test'
) {
    fwrite(STDERR, "ABORT: dedicated MySQL neighbour_marketplace_test database required.\n");
    exit(1);
}

if (($argv[1] ?? '') === '--worker') {
    $decoded = base64_decode($argv[2] ?? '', true);
    $payload = $decoded === false ? null : json_decode($decoded, true);
    $barrier = $argv[3] ?? '';

    if (! is_array($payload) || $barrier === '') {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(1);
    }

    $deadline = microtime(true) + 25;
    while (! file_exists($barrier)) {
        clearstatcache(true, $barrier);
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "Worker barrier timed out.\n");
            exit(1);
        }
        usleep(10000);
    }

    try {
        $order = Order::findOrFail((int) $payload['order_id']);
        $seller = User::findOrFail((int) $payload['seller_id']);
        $confirmed = app(SellerPaymentService::class)->confirm(
            $order,
            $seller,
            (string) $payload['reference']
        );

        $result = [
            'status' => 'success',
            'order_id' => $confirmed->id,
            'payment_status' => $confirmed->payment_status instanceof PaymentStatus
                ? $confirmed->payment_status->value
                : (string) $confirmed->payment_status,
            'payment_reference' => $confirmed->payment_reference,
            'paid_at' => $confirmed->paid_at?->toISOString(),
        ];
    } catch (Throwable $e) {
        $result = [
            'status' => 'error',
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ];
    }

    echo "\nRACE_RESULT:" . json_encode($result, JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

$run = strtolower(Str::random(12));
$barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "seller-payment-race-{$run}.signal";
$processes = [];

try {
    $seller = User::create([
        'name' => 'Race Payment Seller',
        'email' => "pay-seller-{$run}@example.test",
        'phone' => '03' . random_int(100000000, 999999999),
        'password' => 'Password123!',
        'role' => UserRole::SELLER,
        'is_active' => true,
    ]);

    $buyer = User::create([
        'name' => 'Race Payment Buyer',
        'email' => "pay-buyer-{$run}@example.test",
        'phone' => '03' . random_int(100000000, 999999999),
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
        'house_number' => 'House 10',
    ]);

    $category = Category::create([
        'name' => "Payment Race Category {$run}",
        'slug' => "payment-race-category-{$run}",
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $store = Store::create([
        'user_id' => $seller->id,
        'store_name' => "Payment Race Store {$run}",
        'slug' => "payment-race-store-{$run}",
        'house_number' => 'Shop 1',
        'is_open' => true,
        'is_active' => true,
    ]);

    $product = Product::create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'title' => 'Race Payment Item',
        'price' => '300.00',
        'is_available' => true,
        'stock_quantity' => 1,
        'sales_count' => 0,
        'sort_order' => 1,
    ]);

    $batch = app(CheckoutService::class)->checkout([
        'buyer_name' => $buyer->name,
        'buyer_email' => $buyer->email,
        'buyer_phone' => $buyer->phone,
        'buyer_house_number' => $buyer->house_number,
        'fulfillment_type' => 'seller_delivery',
        'payment_method' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ], $buyer->id);

    $order = $batch->orders()->firstOrFail();
    app(OrderStatusService::class)->update($order, $seller, OrderStatus::ACCEPTED);

    $references = ["RACE-A-{$run}", "RACE-B-{$run}"];

    foreach ($references as $reference) {
        $payload = base64_encode(json_encode([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'reference' => $reference,
        ], JSON_THROW_ON_ERROR));

        $process = new Process([
            PHP_BINARY,
            __FILE__,
            '--worker',
            $payload,
            $barrier,
        ], base_path());
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    usleep(1500000);
    file_put_contents($barrier, 'go');

    $results = [];
    foreach ($processes as $index => $process) {
        $process->wait();
        $stdout = $process->getOutput();
        $pos = strrpos($stdout, 'RACE_RESULT:');
        $decoded = $pos === false
            ? null
            : json_decode(trim(substr($stdout, $pos + strlen('RACE_RESULT:'))), true);

        $results[] = is_array($decoded)
            ? array_merge($decoded, [
                'worker' => $index + 1,
                'exit_code' => $process->getExitCode(),
            ])
            : [
                'worker' => $index + 1,
                'status' => 'error',
                'exit_code' => $process->getExitCode(),
                'stdout' => trim($stdout),
                'stderr' => trim($process->getErrorOutput()),
            ];
    }

    $order->refresh();
    $batch->refresh();

    $successes = count(array_filter($results, fn (array $result): bool =>
        ($result['status'] ?? '') === 'success'
        && ($result['exit_code'] ?? -1) === 0
        && ($result['payment_status'] ?? '') === 'paid'
    ));

    $returnedReferences = array_column($results, 'payment_reference');
    $returnedPaidAt = array_column($results, 'paid_at');
    $notificationCount = UserNotification::query()
        ->where('user_id', $buyer->id)
        ->where('type', 'order_payment_confirmed')
        ->count();

    $paymentOutboxCount = DB::table('notification_outboxes')
        ->where('deduplication_key', 'like', "order:{$order->id}:%payment%")
        ->count();

    $summary = [
        'worker_results' => $results,
        'successful_confirmations' => $successes,
        'order_payment_status' => $order->payment_status instanceof PaymentStatus
            ? $order->payment_status->value : (string) $order->payment_status,
        'batch_payment_status' => $batch->payment_status instanceof PaymentStatus
            ? $batch->payment_status->value : (string) $batch->payment_status,
        'stored_reference' => $order->payment_reference,
        'paid_at' => $order->paid_at?->toISOString(),
        'buyer_payment_notifications' => $notificationCount,
        'payment_outbox_records_matching_key' => $paymentOutboxCount,
        'order_count' => Order::where('checkout_batch_id', $batch->id)->count(),
        'remaining_stock' => (int) $product->fresh()->stock_quantity,
    ];

    $passed = $successes === 2
        && count(array_unique($returnedReferences)) === 1
        && count(array_unique($returnedPaidAt)) === 1
        && in_array($order->payment_reference, $references, true)
        && $returnedReferences[0] === $order->payment_reference
        && $returnedPaidAt[0] === $order->paid_at?->toISOString()
        && $order->payment_status === PaymentStatus::PAID
        && $batch->payment_status === PaymentStatus::PAID
        && $notificationCount === 1
        && $summary['order_count'] === 1
        && $summary['remaining_stock'] === 0;

    $summary['passed'] = $passed;

    echo "\n=== CONCURRENT PAYMENT CONFIRMATION TEST ===\n";
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    echo $passed
        ? "\nPASS: Payment confirmed once; reference and notifications stable.\n"
        : "\nFAIL: Payment concurrency contract not satisfied.\n";

    $exitCode = $passed ? 0 : 1;
} finally {
    if (file_exists($barrier)) {
        unlink($barrier);
    }
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
}

exit($exitCode);
