<?php

namespace App\Http\Requests\Admin;

use App\Enums\SellerApplicationStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSellerApplicationsRequest extends FormRequest
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
                Rule::enum(SellerApplicationStatus::class),
            ],
            'per_page' => [
                'sometimes', 'integer', 'min:1', 'max:100',
            ],
            'page' => [
                'sometimes', 'integer', 'min:1',
            ],
        ];
    }
}
