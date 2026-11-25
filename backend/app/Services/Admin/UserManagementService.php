<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserManagementService
{
    public function getUsers(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'created_at',
            ])
            ->when(
                !empty($filters['role']),
                fn ($query) => $query->where(
                    'role',
                    $filters['role']
                )
            )
            ->when(
                !empty($filters['search']),
                function ($query) use ($filters) {
                    $search = $filters['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);
    }
}
