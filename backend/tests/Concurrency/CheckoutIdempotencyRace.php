<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CheckoutBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Refuse to run against any development or production database.
if (
    ! app()->environment('testing')
    || DB::connection()->getDriverName() !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'neighbour_marketplace_test'
) {
    fwrite(STDERR, "ABORTED: dedicated neighbour_marketplace_test MySQL database required.\n");
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
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "Worker synchronization timed out.\n");
            exit(1);
        }
        clearstatcache(true, $barrier);
        usleep(10000);
    }

    try {
        $batch = app(CheckoutService::class)->checkout(
            data: $payload['checkout'],
            buyerId: (int) $payload['buyer_id']
        );
        $result = [
            'status' => 'success',
            'batch_id' => (int) $batch->id,
            'batch_number' => $batch->batch_number,
        ];
    } catch (HttpExceptionInterface $exception) {
        $result = [
            'status' => 'rejected',
            'http_status' => $exception->getStatusCode(),
            'message' => $exception->getMessage(),
        ];
    } catch (Throwable $exception) {
        $result = [
            'status' => 'error',
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ];
    }

    echo "\nRACE_RESULT:" . json_encode($result, JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

$run = strtolower(Str::random(12));
$buyer = User::create([
    'name' => 'Idempotency Race Buyer',
    'email' => "idem-buyer-{$run}@example.test",
    'phone' => '03' . random_int(100000000, 999999999),
    'password' => 'Password123!',
    'role' => UserRole::BUYER,
    'is_active' => true,
    'house_number' => 'House 40',
]);
$seller = User::create([
    'name' => 'Idempotency Race Seller',
    'email' => "idem-seller-{$run}@example.test",
    'phone' => '03' . random_int(100000000, 999999999),
    'password' => 'Password123!',
    'role' => UserRole::SELLER,
    'is_active' => true,
]);
$category = Category::create([
    'name' => "Idempotency Race {$run}",
    'slug' => "idem-category-{$run}",
    'is_active' => true,
    'sort_order' => 1,
]);
$store = Store::create([
    'user_id' => $seller->id,
    'store_name' => "Idempotency Store {$run}",
    'slug' => "idem-store-{$run}",
    'house_number' => 'Shop 40',
    'is_open' => true,
    'is_active' => true,
]);
$product = Product::create([
    'store_id' => $store->id,
    'category_id' => $category->id,
    'title' => 'Single Stock Idempotency Item',
    'price' => '300.00',
    'is_available' => true,
    'stock_quantity' => 1,
    'sales_count' => 0,
    'sort_order' => 1,
]);

$key = (string) Str::uuid();
$checkout = [
    'buyer_name' => $buyer->name,
    'buyer_email' => $buyer->email,
    'buyer_phone' => $buyer->phone,
    'buyer_house_number' => $buyer->house_number,
    'fulfillment_type' => 'seller_delivery',
    'payment_method' => 'cash',
    'idempotency_key' => $key,
    'items' => [
        ['product_id' => $product->id, 'quantity' => 1],
    ],
];

$encoded = base64_encode(json_encode([
    'buyer_id' => $buyer->id,
    'checkout' => $checkout,
], JSON_THROW_ON_ERROR));
$barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "idem-race-{$run}.signal";
$processes = [];

try {
    for ($i = 0; $i < 2; $i++) {
        $process = new Process([
            PHP_BINARY, __FILE__, '--worker', $encoded, $barrier,
        ], base_path());
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    usleep(1500000);
    if (file_put_contents($barrier, 'go') === false) {
        throw new RuntimeException('Could not release workers.');
    }

    $results = [];
    foreach ($processes as $index => $process) {
        $process->wait();
        $stdout = $process->getOutput();
        $markerPos = strrpos($stdout, 'RACE_RESULT:');
        $result = $markerPos === false
            ? null
            : json_decode(trim(substr($stdout, $markerPos + strlen('RACE_RESULT:'))), true);
        $results[] = is_array($result)
            ? array_merge($result, [
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

    $successes = array_values(array_filter(
        $results,
        fn (array $r) => $r['status'] === 'success' && $r['exit_code'] === 0
    ));
    $batchIds = array_unique(array_column($successes, 'batch_id'));
    $batchCount = CheckoutBatch::where('idempotency_key', $key)->count();
    $orderCount = Order::where('store_id', $store->id)->count();
    $itemCount = DB::table('order_items')
        ->where('product_id', $product->id)
        ->count();
    $stock = (int) $product->fresh()->stock_quantity;

    $passed = count($successes) === 2
        && count($batchIds) === 1
        && $batchCount === 1
        && $orderCount === 1
        && $itemCount === 1
        && $stock === 0;

    echo "\n=== CONCURRENT IDEMPOTENCY TEST ===\n";
    echo json_encode([
        'worker_results' => $results,
        'unique_returned_batch_ids' => array_values($batchIds),
        'checkout_batches' => $batchCount,
        'orders' => $orderCount,
        'order_items' => $itemCount,
        'remaining_stock' => $stock,
        'passed' => $passed,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

    if (! $passed) {
        fwrite(STDERR, "\nFAIL: concurrent idempotency contract not satisfied.\n");
        exit(1);
    }

    echo "\nPASS: Same key returned one batch; stock deducted once.\n";
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
    if (file_exists($barrier)) {
        unlink($barrier);
    }
}
