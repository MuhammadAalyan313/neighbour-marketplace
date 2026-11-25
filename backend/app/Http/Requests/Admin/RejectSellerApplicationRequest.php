<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RejectSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'required', 'string', 'min:5', 'max:1000',
            ],
        ];
    }
}
