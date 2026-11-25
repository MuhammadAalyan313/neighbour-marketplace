<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderManagementService
{
    public function getOrders(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'store',
                'items',
            ])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->when(
                isset($filters['store_id']),
                fn ($query) => $query->where(
                    'store_id',
                    $filters['store_id']
                )
            )
            ->when(
                isset($filters['checkout_batch_id']),
                fn ($query) => $query->where(
                    'checkout_batch_id',
                    $filters['checkout_batch_id']
                )
            )
            ->when(
                !empty($filters['search']),
                function ($query) use ($filters) {
                    $search = $filters['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('order_number', 'like', "%{$search}%")
                            ->orWhere('buyer_name', 'like', "%{$search}%");
                    });
                }
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getOrder(Order $order): Order
    {
        return $order->load([
            'store',
            'items',
        ]);
    }
}
