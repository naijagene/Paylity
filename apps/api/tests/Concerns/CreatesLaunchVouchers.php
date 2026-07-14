<?php

namespace Tests\Concerns;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Support\Marketing\LaunchVoucherCodeGenerator;

trait CreatesLaunchVouchers
{
    protected function createLaunchVoucherCampaign(int $amount = 500, int $quantity = 1, array $overrides = []): array
    {
        $generator = app(LaunchVoucherCodeGenerator::class);

        $campaign = LaunchVoucherCampaign::query()->create(array_merge([
            'name' => 'Test Campaign',
            'amount' => $amount,
            'network' => null,
            'generated_count' => $quantity,
            'expires_at' => now()->addMonth(),
            'active' => true,
            'one_per_phone' => true,
            'one_per_email' => false,
            'one_per_device' => true,
            'shared_code' => false,
            'created_by' => 'test',
        ], $overrides));

        $codes = [];

        for ($index = 0; $index < $quantity; $index++) {
            $code = $generator->generateUnique();
            $voucher = LaunchVoucher::query()->create([
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'code' => $code,
                'code_normalized' => $generator->normalize($code),
                'product_type' => 'airtime',
                'amount' => $campaign->amount,
                'network' => $campaign->network,
                'max_redemptions' => 1,
                'expires_at' => $campaign->expires_at,
                'active' => true,
                'one_per_phone' => $campaign->one_per_phone,
                'one_per_email' => $campaign->one_per_email,
                'one_per_device' => $campaign->one_per_device,
                'created_by' => 'test',
            ]);
            $codes[] = $voucher;
        }

        return [
            'campaign' => $campaign,
            'vouchers' => $codes,
            'code' => $codes[0]->code,
        ];
    }
}
