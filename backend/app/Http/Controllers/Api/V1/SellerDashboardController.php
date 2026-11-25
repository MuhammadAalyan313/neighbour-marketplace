<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seller = $request->user();

        /*
        |--------------------------------------------------------------------------
        | 1. Resolve Seller Store
        |--------------------------------------------------------------------------
        |
        | Current V1 supports one active store per seller.
        | The query remains compatible with future multi-store sellers.
        |
        */

        $store = $seller->stores()
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $storeId = $store->id;

        /*
        |--------------------------------------------------------------------------
        | 2. Date Boundaries
        |--------------------------------------------------------------------------
        */

        $today = now()->startOfDay();

        $lastSevenDays = now()
            ->subDays(6)
            ->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | 3. Order Statistics
        |--------------------------------------------------------------------------
        |
        | Instead of running separate COUNT/SUM queries for every stat,
        | calculate all order statistics in one database query.
        |
        */

        $statsRow = Order::query()
            ->where('store_id', $storeId)

            ->selectRaw(
                'COUNT(*) as total_orders'
            )

            ->selectRaw(
                'SUM(
                    CASE
                        WHEN status = ? THEN 1
                        ELSE 0
                    END
                ) as pending_orders',
                [OrderStatus::PENDING->value]
            )

            ->selectRaw(
                'SUM(
                    CASE
                        WHEN status = ? THEN 1
                        ELSE 0
                    END
                ) as accepted_orders',
                [OrderStatus::ACCEPTED->value]
            )

            ->selectRaw(
                'SUM(
                    CASE
                        WHEN status = ? THEN 1
                        ELSE 0
                    END
                ) as delivered_orders',
                [OrderStatus::DELIVERED->value]
            )

            ->selectRaw(
                'SUM(
                    CASE
                        WHEN status = ? THEN 1
                        ELSE 0
                    END
                ) as cancelled_orders',
                [OrderStatus::CANCELLED->value]
            )

            ->selectRaw(
                'SUM(
                    CASE
                        WHEN created_at >= ? THEN 1
                        ELSE 0
                    END
                ) as today_orders',
                [$today]
            )

            ->selectRaw(
                'COALESCE(
                    SUM(
                        CASE
                            WHEN status = ? THEN total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) as total_sales',
                [OrderStatus::DELIVERED->value]
            )

            ->selectRaw(
                'COALESCE(
                    SUM(
                        CASE
                            WHEN status = ?
                            AND created_at >= ?
                            THEN total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) as today_sales',
                [
                    OrderStatus::DELIVERED->value,
                    $today,
                ]
            )

            ->first();

        /*
        |--------------------------------------------------------------------------
        | 4. Product Statistics
        |--------------------------------------------------------------------------
        */

        $productStats = Product::query()
            ->where('store_id', $storeId)
            ->selectRaw(
                'COUNT(*) as product_count'
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN is_available = 1 THEN 1
                        ELSE 0
                    END
                ) as available_product_count'
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 5. Dashboard Stats Response
        |--------------------------------------------------------------------------
        */

        $stats = [
            'total_orders' =>
                (int) $statsRow->total_orders,

            'pending_orders' =>
                (int) $statsRow->pending_orders,

            'accepted_orders' =>
                (int) $statsRow->accepted_orders,

            'delivered_orders' =>
                (int) $statsRow->delivered_orders,

            'cancelled_orders' =>
                (int) $statsRow->cancelled_orders,

            'today_orders' =>
                (int) $statsRow->today_orders,

            'total_sales' =>
                number_format(
                    (float) $statsRow->total_sales,
                    2,
                    '.',
                    ''
                ),

            'today_sales' =>
                number_format(
                    (float) $statsRow->today_sales,
                    2,
                    '.',
                    ''
                ),

            'product_count' =>
                (int) $productStats->product_count,

            'available_product_count' =>
                (int) $productStats->available_product_count,
        ];

        /*
        |--------------------------------------------------------------------------
        | 6. Recent Orders
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::query()
            ->with([
                'items',
            ])
            ->where(
                'store_id',
                $storeId
            )
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 7. Top Products
        |--------------------------------------------------------------------------
        */

        $topProducts = Product::query()
            ->where(
                'store_id',
                $storeId
            )
            ->orderByDesc('sales_count')
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'id',
                'title',
                'price',
                'image',
                'is_available',
                'sales_count',
            ]);

        /*
        |--------------------------------------------------------------------------
        | 8. Sales By Day
        |--------------------------------------------------------------------------
        |
        | Last 7 calendar days including today.
        |
        */

        $salesByDay = Order::query()
            ->where(
                'store_id',
                $storeId
            )
            ->where(
                'status',
                OrderStatus::DELIVERED
            )
            ->where(
                'created_at',
                '>=',
                $lastSevenDays
            )
            ->selectRaw(
                'DATE(created_at) as date'
            )
            ->selectRaw(
                'COALESCE(SUM(total_amount), 0) as total'
            )
            ->selectRaw(
                'COUNT(*) as orders'
            )
            ->groupBy(
                DB::raw('DATE(created_at)')
            )
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' =>
                        $row->date,

                    'total' =>
                        number_format(
                            (float) $row->total,
                            2,
                            '.',
                            ''
                        ),

                    'orders' =>
                        (int) $row->orders,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 9. Store Information
        |--------------------------------------------------------------------------
        */

        $storeData = [
            'id' =>
                $store->id,

            'store_name' =>
                $store->store_name,

            'slug' =>
                $store->slug,

            'house_number' =>
                $store->house_number,

            'is_open' =>
                (bool) $store->is_open,

            'is_active' =>
                (bool) $store->is_active,

            'rating_average' =>
                (string) $store->rating_average,

            'reviews_count' =>
                (int) $store->reviews_count,
        ];

        /*
        |--------------------------------------------------------------------------
        | 10. Final Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'store' =>
                    $storeData,

                'stats' =>
                    $stats,

                'recent_orders' =>
                    $recentOrders,

                'top_products' =>
                    $topProducts,

                'sales_by_day' =>
                    $salesByDay,
            ],
        ]);
    }
}