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

/*
|--------------------------------------------------------------------------
| Database Safety
|--------------------------------------------------------------------------
*/

$database = DB::connection();

if (
    ! app()->environment('testing')
    || $database->getDriverName() !== 'mysql'
    || $database->getDatabaseName() !== 'neighbour_marketplace_test'
) {
    fwrite(
        STDERR,
        "ABORTED: Dedicated MySQL testing database required.\n"
    );

    exit(1);
}

/*
|--------------------------------------------------------------------------
| Independent Worker Process
|--------------------------------------------------------------------------
*/

if (($argv[1] ?? null) === '--worker') {
    $encoded = $argv[2] ?? '';
    $barrier = $argv[3] ?? '';

    $decoded = base64_decode($encoded, true);

    $data = $decoded !== false
        ? json_decode($decoded, true)
        : null;

    if (! is_array($data) || $barrier === '') {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(1);
    }

    $deadline = microtime(true) + 20;

    while (! file_exists($barrier)) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "Worker barrier timeout.\n");
            exit(1);
        }

        clearstatcache(true, $barrier);
        usleep(10000);
    }

    try {
        $batch = app(CheckoutService::class)->checkout(
            data: $data['checkout'],
            buyerId: (int) $data['buyer_id']
        );

        $result = [
            'status' => 'success',
            'batch_id' => $batch->id,
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

    /*
    |--------------------------------------------------------------------------
    | Machine-readable result marker
    |--------------------------------------------------------------------------
    |
    | Ignore incidental PHP warnings when the parent parses
    | the worker's output.
    |
    */

    echo "\nRACE_RESULT:";
    echo json_encode($result, JSON_THROW_ON_ERROR);
    echo "\n";

    exit(0);
}

/*
|--------------------------------------------------------------------------
| Create Independent Test Data
|--------------------------------------------------------------------------
*/

$run = strtolower(Str::random(12));

$seller = User::create([
    'name' => 'Concurrency Seller',
    'email' => "race-seller-{$run}@example.test",
    'phone' => '03' . random_int(100000000, 999999999),
    'password' => 'Password123!',
    'role' => UserRole::SELLER,
    'is_active' => true,
]);

$buyers = [];

foreach ([1, 2] as $number) {
    $buyers[] = User::create([
        'name' => "Concurrency Buyer {$number}",
        'email' => "race-buyer-{$number}-{$run}@example.test",
        'phone' => '03' . random_int(100000000, 999999999),
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
        'house_number' => "House {$number}",
    ]);
}

$category = Category::create([
    'name' => "Race Category {$run}",
    'slug' => "race-category-{$run}",
    'is_active' => true,
    'sort_order' => 1,
]);

$store = Store::create([
    'user_id' => $seller->id,
    'store_name' => "Race Store {$run}",
    'slug' => "race-store-{$run}",
    'house_number' => 'Shop 1',
    'is_open' => true,
    'is_active' => true,
]);

$product = Product::create([
    'store_id' => $store->id,
    'category_id' => $category->id,
    'title' => 'Last Available Product',
    'price' => '300.00',
    'is_available' => true,
    'stock_quantity' => 1,
    'sales_count' => 0,
    'sort_order' => 1,
]);

/*
|--------------------------------------------------------------------------
| Start Two Independent PHP Processes
|--------------------------------------------------------------------------
*/

$barrier = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . "marketplace-race-{$run}.signal";

$processes = [];

try {
    foreach ($buyers as $buyer) {
        $checkout = [
            'buyer_name' => $buyer->name,
            'buyer_email' => $buyer->email,
            'buyer_phone' => $buyer->phone,
            'buyer_house_number' => $buyer->house_number,
            'fulfillment_type' => 'seller_delivery',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $payload = base64_encode(json_encode([
            'buyer_id' => $buyer->id,
            'checkout' => $checkout,
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

    // Give workers time to bootstrap independently.
    usleep(1500000);

    file_put_contents($barrier, 'go');

    /*
    |--------------------------------------------------------------------------
    | Collect Actual Worker Results
    |--------------------------------------------------------------------------
    */

    $results = [];

    foreach ($processes as $index => $process) {
        $process->wait();

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        $marker = 'RACE_RESULT:';

        $position = strrpos($stdout, $marker);

        $decoded = null;

        if ($position !== false) {
            $json = trim(
                substr(
                    $stdout,
                    $position + strlen($marker)
                )
            );

            $decoded = json_decode($json, true);
        }

        if (is_array($decoded)) {
            $results[] = array_merge($decoded, [
                'worker' => $index + 1,
                'exit_code' => $process->getExitCode(),
            ]);
        } else {
            $results[] = [
                'worker' => $index + 1,
                'status' => 'error',
                'exit_code' => $process->getExitCode(),
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Database State
    |--------------------------------------------------------------------------
    */

    $successes = count(array_filter(
        $results,
        fn (array $result): bool =>
            ($result['status'] ?? null) === 'success'
            && ($result['exit_code'] ?? null) === 0
    ));

    $rejections = count(array_filter(
        $results,
        fn (array $result): bool =>
            ($result['status'] ?? null) === 'rejected'
            && (int) ($result['http_status'] ?? 0) === 422
            && ($result['exit_code'] ?? null) === 0
    ));

    $stock = (int) $product->fresh()->stock_quantity;

    $orderCount = Order::query()
        ->where('store_id', $store->id)
        ->count();

    $batchCount = CheckoutBatch::query()
        ->whereHas('orders', fn ($query) =>
            $query->where('store_id', $store->id)
        )
        ->count();

    $itemCount = DB::table('order_items')
        ->join(
            'orders',
            'orders.id',
            '=',
            'order_items.order_id'
        )
        ->where('orders.store_id', $store->id)
        ->where('order_items.product_id', $product->id)
        ->count();

    $summary = [
        'worker_results' => $results,
        'successful_checkouts' => $successes,
        'stock_rejections' => $rejections,
        'remaining_stock' => $stock,
        'orders' => $orderCount,
        'batches' => $batchCount,
        'order_items' => $itemCount,
    ];

    echo "\n=== REAL MYSQL CONCURRENCY TEST ===\n";

    echo json_encode(
        $summary,
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );

    echo "\n";

    $passed =
        $successes === 1
        && $rejections === 1
        && $stock === 0
        && $orderCount === 1
        && $batchCount === 1
        && $itemCount === 1;

    if (! $passed) {
        fwrite(
            STDERR,
            "\nFAIL: Concurrency expectations not met.\n"
        );

        exit(1);
    }

    echo "\nPASS: No overselling or duplicate checkout.\n";
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
