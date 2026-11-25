<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Http\Requests\MarketplaceProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function stores(Request $request): JsonResponse
    {
        // Prevent unbounded page sizes on the public endpoint.
        $perPage = max(1, min(50, $request->integer('per_page', 20)));

        $stores = Store::query()
            ->where('is_active', true)
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $query->where(
                        'store_name',
                        'like',
                        '%' . $request->string('search') . '%'
                    );
                }
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => StoreResource::collection($stores),
            'meta' => [
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
            ],
        ]);
    }


    public function store(Store $store): JsonResponse
    {
        // Route model binding resolves the slug. Hide inactive or closed stores.
        abort_unless(
            $store->is_active && $store->is_open,
            404,
            'Store is currently unavailable.'
        );

        // The existing Product::available() scope handles unlimited stock (NULL)
        // and excludes zero-stock or explicitly unavailable products.
        $store->load([
            'products' => function ($query) {
                $query->available()
                    ->orderBy('sort_order')
                    ->orderByDesc('id');
            },
            'products.category',
        ]);

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }


    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }


 public function products(MarketplaceProductRequest $request): JsonResponse
{
    $validated = $request->validated();

    $products = Product::query()
        ->with([
            'store',
            'category',
        ])
        ->where('is_available', true)
        ->whereHas('store', function ($query) {
            $query
                ->where('is_active', true)
                ->where('is_open', true);
        })

        ->when(
            ! empty($validated['search']),
            function ($query) use ($validated) {
                $search = trim($validated['search']);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        )

        ->when(
            isset($validated['category_id']),
            function ($query) use ($validated) {
                $query->where(
                    'category_id',
                    $validated['category_id']
                );
            }
        )

        ->when(
            isset($validated['store_id']),
            function ($query) use ($validated) {
                $query->where(
                    'store_id',
                    $validated['store_id']
                );
            }
        )

        ->when(
            isset($validated['min_price']),
            function ($query) use ($validated) {
                $query->where(
                    'price',
                    '>=',
                    $validated['min_price']
                );
            }
        )

        ->when(
            isset($validated['max_price']),
            function ($query) use ($validated) {
                $query->where(
                    'price',
                    '<=',
                    $validated['max_price']
                );
            }
        )

        ->when(
            ! empty($validated['sort']),
            function ($query) use ($validated) {
                match ($validated['sort']) {
                    'price_asc' => $query
                        ->orderBy('price', 'asc')
                        ->orderBy('id', 'desc'),

                    'price_desc' => $query
                        ->orderBy('price', 'desc')
                        ->orderBy('id', 'desc'),

                    'popular' => $query
                        ->orderByDesc('sales_count')
                        ->orderByDesc('id'),

                    'newest' => $query
                        ->orderByDesc('id'),

                    default => $query
                        ->orderBy('sort_order')
                        ->orderByDesc('id'),
                };
            },
            function ($query) {
                $query
                    ->orderBy('sort_order')
                    ->orderByDesc('id');
            }
        );

    $perPage = $validated['per_page'] ?? 20;

    $products = $products->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => ProductResource::collection($products),
        'meta' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ],
    ]);
}
}