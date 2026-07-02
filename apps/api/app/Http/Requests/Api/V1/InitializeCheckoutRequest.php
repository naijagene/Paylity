<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitializeCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', 'string', Rule::in(['airtime', 'data', 'electricity'])],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'product_amount' => ['required', 'integer', 'min:50', 'max:1000000'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
