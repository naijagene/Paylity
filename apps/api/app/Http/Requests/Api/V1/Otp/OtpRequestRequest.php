<?php

namespace App\Http\Requests\Api\V1\Otp;

use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'string', Rule::in(array_column(OtpPurpose::cases(), 'value'))],
            'reference' => ['nullable', 'string', 'max:64'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'product_type' => ['nullable', 'string', Rule::in(['airtime', 'data', 'electricity'])],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
