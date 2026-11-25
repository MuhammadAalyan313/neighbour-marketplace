<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSellerProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() === true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'image' => [
                'nullable',
                'string',
                'max:500',
            ],

            'badge' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_available' => [
                'sometimes',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ];
    }
}