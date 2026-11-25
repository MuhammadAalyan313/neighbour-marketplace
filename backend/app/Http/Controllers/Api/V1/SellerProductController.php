<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()
            ->stores()
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'availability' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $products = Product::query()
            ->with('category')
            ->where('store_id', $store->id)
            ->when(
                filled($validated['search'] ?? null),
                function ($query) use ($validated) {
                    $search = trim($validated['search']);

                    $query->where(function ($query) use ($search) {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                isset($validated['category_id']),
                fn ($query) => $query->where(
                    'category_id',
                    $validated['category_id']
                )
            )
            ->when(
                array_key_exists('availability', $validated),
                fn ($query) => $query->where(
                    'is_available',
                    $request->boolean('availability')
                )
            )
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

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

    public function store(Request $request): JsonResponse
    {
        $store = $request->user()
            ->stores()
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'badge' => ['nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'stock_quantity' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! Category::query()
            ->whereKey($validated['category_id'])
            ->where('is_active', true)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Selected category is inactive or unavailable.',
            ], 422);
        }

        $product = $store->products()->create([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'image' => $validated['image'] ?? null,
            'badge' => $validated['badge'] ?? null,
            'is_available' => $validated['is_available'] ?? true,
            'stock_quantity' => $validated['stock_quantity'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'sales_count' => 0,
        ]);

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(
        Request $request,
        Product $product
    ): JsonResponse {
        $ownsProduct = $request->user()
            ->stores()
            ->whereKey($product->store_id)
            ->where('is_active', true)
            ->exists();

        abort_unless(
            $ownsProduct,
            403,
            'You are not authorized to view this product.'
        );

        $product->load('category');

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    public function update(
        Request $request,
        Product $product
    ): JsonResponse {
        $ownsProduct = $request->user()
            ->stores()
            ->whereKey($product->store_id)
            ->where('is_active', true)
            ->exists();

        abort_unless(
            $ownsProduct,
            403,
            'You are not authorized to update this product.'
        );

        $validated = $request->validate([
            'category_id' => [
                'sometimes',
                'integer',
                'exists:categories,id',
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'stock_quantity' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($validated['category_id'])) {
            $categoryExists = Category::query()
                ->whereKey($validated['category_id'])
                ->where('is_active', true)
                ->exists();

            if (! $categoryExists) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Selected category is inactive or unavailable.',
                ], 422);
            }
        }

        DB::transaction(function () use ($product, $validated) {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct->update($validated);
        }, 3);

        $product->refresh()->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product),
        ]);
    }

    public function destroy(
        Request $request,
        Product $product
    ): JsonResponse {
        $ownsProduct = $request->user()
            ->stores()
            ->whereKey($product->store_id)
            ->where('is_active', true)
            ->exists();

        abort_unless(
            $ownsProduct,
            403,
            'You are not authorized to delete this product.'
        );

        $disabled = DB::transaction(function () use ($product) {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasOrderHistory = OrderItem::query()
                ->where('product_id', $lockedProduct->id)
                ->exists();

            if ($hasOrderHistory) {
                $lockedProduct->update([
                    'is_available' => false,
                ]);

                return true;
            }

            $lockedProduct->delete();

            return false;
        }, 3);

        if ($disabled) {
            return response()->json([
                'success' => true,
                'message' =>
                    'Product has order history, so it was disabled instead of deleted.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
