<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'otp:cleanup';

    protected $description =
        'Remove expired email and phone verification codes.';

    public function handle(): int
    {
        try {
            $totalDeleted = 0;

            // Process in batches to avoid long-running
            // database locks on shared hosting.
            do {
                $ids = DB::table('verification_codes')
                    ->where('expires_at', '<=', now())
                    ->orderBy('id')
                    ->limit(500)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted = DB::table('verification_codes')
                    ->whereIn('id', $ids)
                    ->where('expires_at', '<=', now())
                    ->delete();

                $totalDeleted += $deleted;

            } while ($ids->count() === 500);

            $this->info(
                "{$totalDeleted} expired OTP records deleted."
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            Log::error('OTP cleanup failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $this->error(
                'OTP cleanup failed. Check application logs.'
            );

            return self::FAILURE;
        }
    }
}
