<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ValidateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'product_type' => ['required', 'string', 'in:airtime'],
            'product_amount' => ['required', 'integer', 'min:1'],
            'network' => ['nullable', 'string', 'max:32'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
