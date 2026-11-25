<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PushSubscriptionService
{
    public function subscribe(User $user, array $data): void
    {
        $endpoint = $data['endpoint'];
        $hash = hash('sha256', $endpoint);

        try {
            DB::transaction(function () use (
                $user,
                $data,
                $endpoint,
                $hash
            ) {
                $subscription = PushSubscription::query()
                    ->where('endpoint_hash', $hash)
                    ->lockForUpdate()
                    ->first();

                // Prevent another user from claiming an
                // already registered push endpoint.
                if (
                    $subscription &&
                    (int) $subscription->user_id !== (int) $user->id
                ) {
                    abort(
                        409,
                        'This push subscription belongs to another account.'
                    );
                }

                if ($subscription) {
                    $subscription->update([
                        'endpoint' => $endpoint,
                        'p256dh' => $data['keys']['p256dh'],
                        'auth' => $data['keys']['auth'],
                    ]);

                    return;
                }

                PushSubscription::query()->create([
                    'user_id' => $user->id,
                    'endpoint_hash' => $hash,
                    'endpoint' => $endpoint,
                    'p256dh' => $data['keys']['p256dh'],
                    'auth' => $data['keys']['auth'],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Concurrent first registrations: let the database
            // unique constraint prevent endpoint hijacking.
            throw new ConflictHttpException(
                'Push subscription was registered concurrently. Please retry.',
                $exception
            );
        }
    }

    public function unsubscribe(
        User $user,
        string $endpoint
    ): void {
        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where(
                'endpoint_hash',
                hash('sha256', $endpoint)
            )
            ->delete();
    }
}
