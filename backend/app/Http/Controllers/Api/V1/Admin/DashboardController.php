<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Models\SellerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $orderStats = Order::query()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_orders',
                ['pending']
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_orders',
                ['delivered']
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as delivered_revenue',
                ['delivered']
            )
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'buyers' => User::where('role', UserRole::BUYER->value)->count(),
                    'sellers' => User::where('role', UserRole::SELLER->value)->count(),
                ],
                'stores' => [
                    'total' => Store::count(),
                    'active' => Store::where('is_active', true)->count(),
                ],
                'products' => [
                    'total' => Product::count(),
                ],
                'seller_applications' => [
                    'pending' => SellerApplication::pending()->count(),
                ],
                'orders' => [
                    'total' => (int) $orderStats->total_orders,
                    'pending' => (int) $orderStats->pending_orders,
                    'delivered' => (int) $orderStats->delivered_orders,
                ],
                'revenue' => [
                    'delivered_orders_total' => (string) $orderStats->delivered_revenue,
                ],
            ],
        ]);
    }
}
