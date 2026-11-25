<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() === true;
    }

    public function rules(): array
    {
        return [
            'store_name' => [
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stores', 'slug')
                    ->ignore(
                        $this->user()?->stores()->first()?->id
                    ),
            ],

            'house_number' => [
                'required',
                'string',
                'max:100',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'bonus_text' => [
                'nullable',
                'string',
                'max:500',
            ],

            'logo' => [
                'nullable',
                'string',
                'max:500',
            ],

            'is_open' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}