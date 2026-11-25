<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationInboxService
{

public function list(
    User $user,
    array $filters = []
): LengthAwarePaginator {
    $perPage = min(
        50,
        max(1, (int) ($filters['per_page'] ?? 20))
    );

    return UserNotification::query()
        ->where('user_id', $user->id)
        ->when(
            ($filters['status'] ?? 'all') === 'unread',
            fn ($query) => $query->whereNull('read_at')
        )
        ->when(
            ($filters['status'] ?? 'all') === 'read',
            fn ($query) => $query->whereNotNull('read_at')
        )
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->paginate($perPage);
}


    public function unreadCount(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(User $user, int $id): UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }


public function create(
    User $user,
    string $type,
    string $title,
    string $body,
    ?string $url = null,
    array $data = [],
    ?string $deduplicationKey = null
): UserNotification {
    $attributes = [
        'user_id' => $user->id,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'data' => $data,
    ];

    if ($deduplicationKey !== null) {
        // Scope each deduplication key to the recipient.
        $key = 'user:'.$user->id.':'.$deduplicationKey;

        return UserNotification::query()->createOrFirst(
            [
                'deduplication_key' => $key,
            ],
            $attributes
        );
    }

    return UserNotification::query()->create($attributes);
}

}