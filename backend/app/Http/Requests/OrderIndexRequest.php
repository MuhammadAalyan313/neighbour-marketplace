<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(OrderStatus::class),
            ],

            'payment_status' => [
                'sometimes',
                'nullable',
                Rule::enum(PaymentStatus::class),
            ],

            'store_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:stores,id',
            ],

            'from' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'to' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:from',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}