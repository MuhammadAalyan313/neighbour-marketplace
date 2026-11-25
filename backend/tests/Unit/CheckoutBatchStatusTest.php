<?php

use App\Enums\PaymentStatus;
use App\Models\CheckoutBatch;

it('uses enum backed comparison for paid checkout batches', function () {
    $batch = new CheckoutBatch();
    $batch->payment_status = PaymentStatus::PAID;
    expect($batch->isPaid())->toBeTrue();
    $batch->payment_status = PaymentStatus::UNPAID;
    expect($batch->isPaid())->toBeFalse();
});
