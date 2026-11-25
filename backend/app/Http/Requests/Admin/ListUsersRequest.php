<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'sometimes',
                Rule::enum(UserRole::class),
            ],
            'search' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}
