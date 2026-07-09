<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyElectricityMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disco' => ['required', 'string', 'max:32'],
            'meter_number' => ['required', 'string', 'max:32'],
            'meter_type' => ['required', 'string', Rule::in(['prepaid', 'postpaid'])],
        ];
    }
}
