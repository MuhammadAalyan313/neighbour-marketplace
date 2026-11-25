<?php

namespace App\Services\Admin;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class StoreManagementService
{
    public function getStores(array $filters): LengthAwarePaginator
    {
        return Store::query()
            ->withCount('products')
            ->when(
                !empty($filters['search']),
                function ($query) use ($filters) {
                    $search = $filters['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('store_name', 'like', "%{$search}%")
                            ->orWhere('house_number', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where(
                    'is_active',
                    $filters['is_active']
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getStore(Store $store): Store
    {
        return $store->loadCount('products');
    }

    public function updateStatus(Store $store, bool $isActive): Store
    {
        return DB::transaction(function () use ($store, $isActive) {
            $lockedStore = Store::query()
                ->lockForUpdate()
                ->findOrFail($store->id);

            $lockedStore->is_active = $isActive;
            $lockedStore->save();

            return $lockedStore->refresh();
        });
    }
}
