<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSellerStoreRequest;
use App\Http\Requests\UpdateSellerStoreRequest;
use App\Http\Resources\StoreResource;
use Illuminate\Http\JsonResponse;
use App\Enums\SellerApplicationStatus;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SellerStoreController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $store = $request->user()
            ->stores()
            ->with('products.category')
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a store yet.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
        ]);
    }
 
    
public function store(
    CreateSellerStoreRequest $request
): JsonResponse {
    $validated = $request->validated();

    $store = DB::transaction(function () use ($request, $validated) {

        // Lock seller to prevent concurrent store creation.
        $user = User::query()
            ->whereKey($request->user()->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(
            $user->is_active && $user->isSeller(),
            403,
            'Only active sellers can create stores.'
        );

        abort_if(
            $user->stores()->exists(),
            409,
            'You already have a store.'
        );

        // Store can only be created against an approved application.
        $application = SellerApplication::query()
            ->where('user_id', $user->id)
            ->where('status', SellerApplicationStatus::APPROVED)
            ->latest('id')
            ->first();

        abort_unless(
            $application,
            403,
            'An approved seller application is required.'
        );

        abort_unless(
            $validated['slug'] === $application->store_slug,
            422,
            'Store slug must match your approved application.'
        );

        // Admin-approved application identity is preserved.
        $store = $user->stores()->create([
            ...$validated,

            'store_name' => $application->store_name,
            'slug' => $application->store_slug,
            'house_number' => $application->house_number,

            'is_open' => false,
            'is_active' => true,
        ]);

        return $store;
    }, 3);

    return response()->json([
        'success' => true,
        'message' => 'Store created successfully. Add products and open your store when ready.',
        'data' => new StoreResource($store),
    ], 201);
}


    public function update(
        UpdateSellerStoreRequest $request
    ): JsonResponse {

        $store = $request->user()
            ->stores()
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $store->update(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully.',
            'data' => new StoreResource(
                $store->fresh('products.category')
            ),
        ]);
    }
    
public function updatePaymentMethods(Request $request): JsonResponse
{
    $user = $request->user();

    abort_unless(
        $user && $user->is_active && $user->isSeller(),
        403,
        'Only active sellers can update payment methods.'
    );

    $validated = $request->validate([
        'payment_methods' => [
            'required',
            'array:easypaisa,jazzcash,bank_transfer',
            'min:1',
        ],

        'payment_methods.easypaisa' => [
            'sometimes',
            'array:enabled,account_title,account_number',
        ],
        'payment_methods.easypaisa.enabled' => [
            'required_with:payment_methods.easypaisa',
            'boolean',
        ],
        'payment_methods.easypaisa.account_title' => [
            'nullable',
            'string',
            'max:150',
        ],
        'payment_methods.easypaisa.account_number' => [
            'nullable',
            'string',
            'max:30',
        ],

        'payment_methods.jazzcash' => [
            'sometimes',
            'array:enabled,account_title,account_number',
        ],
        'payment_methods.jazzcash.enabled' => [
            'required_with:payment_methods.jazzcash',
            'boolean',
        ],
        'payment_methods.jazzcash.account_title' => [
            'nullable',
            'string',
            'max:150',
        ],
        'payment_methods.jazzcash.account_number' => [
            'nullable',
            'string',
            'max:30',
        ],

        'payment_methods.bank_transfer' => [
            'sometimes',
            'array:enabled,account_title,account_number,bank_name,iban',
        ],
        'payment_methods.bank_transfer.enabled' => [
            'required_with:payment_methods.bank_transfer',
            'boolean',
        ],
        'payment_methods.bank_transfer.account_title' => [
            'nullable',
            'string',
            'max:150',
        ],
        'payment_methods.bank_transfer.account_number' => [
            'nullable',
            'string',
            'max:50',
        ],
        'payment_methods.bank_transfer.bank_name' => [
            'nullable',
            'string',
            'max:150',
        ],
        'payment_methods.bank_transfer.iban' => [
            'nullable',
            'string',
            'max:50',
        ],
    ]);

    $methods = $validated['payment_methods'];

    /*
    |--------------------------------------------------------------------------
    | Validate Enabled Payment Methods
    |--------------------------------------------------------------------------
    */

    foreach ($methods as $method => $account) {
        if (! (bool) $account['enabled']) {
            continue;
        }

        $requiredFields = $method === 'bank_transfer'
            ? ['account_title', 'bank_name']
            : ['account_title', 'account_number'];

        foreach ($requiredFields as $field) {
            if (trim((string) ($account[$field] ?? '')) === '') {
                throw \Illuminate\Validation\ValidationException
                    ::withMessages([
                        "payment_methods.{$method}.{$field}" => [
                            "The {$field} field is required when {$method} is enabled.",
                        ],
                    ]);
            }
        }

        if (
            $method === 'bank_transfer'
            && trim((string) ($account['account_number'] ?? '')) === ''
            && trim((string) ($account['iban'] ?? '')) === ''
        ) {
            throw \Illuminate\Validation\ValidationException
                ::withMessages([
                    'payment_methods.bank_transfer.account_number' => [
                        'Provide a bank account number or IBAN.',
                    ],
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Seller's Own Store
    |--------------------------------------------------------------------------
    */

    $store = $user->stores()->first();

    if (! $store) {
        return response()->json([
            'success' => false,
            'message' => 'Store not found.',
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Preserve Existing Methods
    |--------------------------------------------------------------------------
    */

    $existing = $store->payment_methods ?? [];

    foreach ($methods as $method => $account) {
        $existing[$method] = array_merge(
            $existing[$method] ?? [],
            $account
        );

        $existing[$method]['enabled'] =
            (bool) $account['enabled'];
    }

    $store->update([
        'payment_methods' => $existing,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Payment methods updated successfully.',
        'data' => [
            'store_id' => $store->id,
            'payment_methods' => $store->fresh()->payment_methods,
        ],
    ]);
}

}