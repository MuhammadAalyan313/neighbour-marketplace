<?php

use App\Http\Controllers\Api\V1\SellerStoreController;
use App\Services\OrderReceiptService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes every receipt API required by notification delivery', function () {
    expect(method_exists(OrderReceiptService::class, 'subject'))->toBeTrue();
    expect(method_exists(OrderReceiptService::class, 'body'))->toBeTrue();
    expect(method_exists(OrderReceiptService::class, 'data'))->toBeTrue();
    expect(method_exists(OrderReceiptService::class, 'message'))->toBeTrue();
});

it('has the seller payment methods controller endpoint implementation', function () {
    expect(method_exists(SellerStoreController::class, 'updatePaymentMethods'))->toBeTrue();
});

it('has persisted store payment methods for digital checkout', function () {
    expect(Schema::hasColumn('stores', 'payment_methods'))->toBeTrue();
});
