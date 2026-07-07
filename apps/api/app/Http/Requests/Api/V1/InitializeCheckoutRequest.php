<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
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
        /** @var SystemSettingsService $settings */
        $settings = app(SystemSettingsService::class);

        $minAmount = $settings->getInt(SystemSettingKeys::MIN_PRODUCT_AMOUNT, 50);
        $maxAmount = $settings->getInt(SystemSettingKeys::MAX_PRODUCT_AMOUNT, 1_000_000);

        return [
            'product_type' => ['required', 'string', Rule::in(['airtime', 'data', 'electricity'])],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'product_amount' => ['required', 'integer', 'min:'.$minAmount, 'max:'.$maxAmount],
            'payload' => ['nullable', 'array'],
            'verification_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
