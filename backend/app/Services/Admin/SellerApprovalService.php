<?php

namespace App\Services\Admin;

use App\Enums\SellerApplicationStatus;
use App\Enums\UserRole;
use App\Models\SellerApplication;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SellerApprovalService
{
    public function approve(
        SellerApplication $application,
        int $adminId
    ): SellerApplication {
        return DB::transaction(function () use (
            $application,
            $adminId
        ) {
            $application = SellerApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            abort_unless(
                $application->status === SellerApplicationStatus::PENDING,
                409,
                'Only pending applications can be approved.'
            );

            $user = $application->user()
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $user->is_active,
                422,
                'Applicant account is inactive.'
            );

            abort_unless(
                $user->role === UserRole::BUYER,
                409,
                'Only buyer accounts can become sellers.'
            );

            abort_if(
                $user->stores()->exists(),
                409,
                'Applicant already owns a store.'
            );

            abort_if(
                Store::query()
                    ->where('slug', $application->store_slug)
                    ->exists(),
                409,
                'Requested store slug is already taken.'
            );

            $user->role = UserRole::SELLER;
            $user->save();

            $application->update([
                'status' => SellerApplicationStatus::APPROVED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $application->load('user', 'reviewer');
        }, 3);
    }

    public function reject(
        SellerApplication $application,
        int $adminId,
        string $reason
    ): SellerApplication {
        return DB::transaction(function () use (
            $application,
            $adminId,
            $reason
        ) {
            $application = SellerApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            abort_unless(
                $application->status === SellerApplicationStatus::PENDING,
                409,
                'Only pending applications can be rejected.'
            );

            $application->update([
                'status' => SellerApplicationStatus::REJECTED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $application->load('user', 'reviewer');
        }, 3);
    }

    public function listApplications(
        array $filters
    ): LengthAwarePaginator {
        return SellerApplication::query()
            ->with('user:id,name,email')
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);
    }
}
