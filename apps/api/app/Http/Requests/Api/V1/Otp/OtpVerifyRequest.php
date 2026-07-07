<?php

namespace App\Http\Requests\Api\V1\Otp;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp_reference' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'min:4', 'max:8'],
        ];
    }
}
