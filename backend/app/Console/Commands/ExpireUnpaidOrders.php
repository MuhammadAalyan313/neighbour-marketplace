<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description =
        'Expire pending unpaid digital orders after the configured payment window.';

    public function handle(OrderStatusService $orderStatusService): int
    {
        if (! config('marketplace.order_expiry_enabled', false)) {
            $this->info('Automatic order expiry is disabled.');

            return self::SUCCESS;
        }

        $minutes = (int) config(
            'marketplace.order_expiry_minutes',
            60
        );

        if ($minutes < 1) {
            $this->error('Invalid expiry configuration.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);

        $expired = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('Checking expired unpaid digital orders...');

        Order::query()
            ->with('store.owner')
            ->where('status', OrderStatus::PENDING->value)
            ->where('payment_status', PaymentStatus::UNPAID->value)
            ->whereIn('payment_method', [
                'easypaisa',
                'jazzcash',
                'bank_transfer',
            ])
            ->where('created_at', '<=', $cutoff)
            ->whereHas('store.owner')
            ->chunkById(100, function ($orders) use (
                $orderStatusService,
                &$expired,
                &$skipped,
                &$failed
            ) {
                foreach ($orders as $order) {
                    try {
                        $seller = $order->store?->owner;

                        if (! $seller) {
                            $skipped++;
                            continue;
                        }

                        $orderStatusService->update(
                            order: $order,
                            seller: $seller,
                            newStatus: OrderStatus::CANCELLED,
                            cancellationReason:
                                'Unpaid digital order expired automatically.',
                            expectedCurrentStatus: OrderStatus::PENDING
                        );

                        $expired++;
                    } catch (HttpExceptionInterface $exception) {
                        if (in_array(
                            $exception->getStatusCode(),
                            [403, 409],
                            true
                        )) {
                            // Seller accepted the order or payment
                            // was confirmed before the database lock.
                            $skipped++;
                            continue;
                        }

                        $failed++;

                        Log::warning(
                            'Order expiry rejected.',
                            [
                                'order_id' => $order->id,
                                'status_code' =>
                                    $exception->getStatusCode(),
                                'error' => $exception->getMessage(),
                            ]
                        );
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error(
                            'Order expiry failed.',
                            [
                                'order_id' => $order->id,
                                'exception' => get_class($exception),
                                'error' => $exception->getMessage(),
                            ]
                        );
                    }
                }
            });

        $this->info(
            "Expired: {$expired}, Skipped: {$skipped}, Failed: {$failed}"
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
