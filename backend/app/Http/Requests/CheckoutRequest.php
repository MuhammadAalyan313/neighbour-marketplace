<?php

namespace App\Http\Requests;

use App\Enums\FulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_name' => [
                'required',
                'string',
                'max:150',
            ],

            'buyer_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'buyer_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'buyer_house_number' => [
                'required',
                'string',
                'max:100',
            ],

            'fulfillment_type' => [
                'required',
                new Enum(FulfillmentType::class),
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'items.*' => [
                'required',
                'array:product_id,quantity',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    'cash',
                    'easypaisa',
                    'jazzcash',
                    'bank_transfer',
                ]),
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
