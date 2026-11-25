<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'pending',
                    'accepted',
                    'delivered',
                    'cancelled',
                ]),
            ],
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
            'checkout_batch_id' => [
                'sometimes', 'integer', 'exists:checkout_batches,id',
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
