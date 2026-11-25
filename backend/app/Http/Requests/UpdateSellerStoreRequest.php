<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() === true;
    }

    public function rules(): array
    {
        $store = $this->user()?->stores()->first();

        return [
            'store_name' => [
                'sometimes', 'required', 'string', 'max:150',
            ],
            'slug' => [
                'sometimes', 'required', 'string', 'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stores', 'slug')->ignore($store?->id),
            ],
            'house_number' => [
                'sometimes', 'required', 'string', 'max:100',
            ],
            'description' => [
                'sometimes', 'nullable', 'string', 'max:2000',
            ],
            'bonus_text' => [
                'sometimes', 'nullable', 'string', 'max:500',
            ],
            'logo' => [
                'sometimes', 'nullable', 'string', 'max:500',
            ],
            'is_open' => [
                'sometimes', 'boolean',
            ],
        ];
    }
}
