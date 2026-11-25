<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketplaceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'store_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:stores,id',
            ],

            'min_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'price_asc',
                    'price_desc',
                    'popular',
                    'newest',
                ]),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }
}