<?php

namespace Tests\Concerns;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Models\Transaction;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use App\Support\Marketing\VoucherIdentityNormalizer;

trait CreatesLaunchVouchers
{
    /**
     * @return array{campaign: LaunchVoucherCampaign, vouchers: array<int, LaunchVoucher>, code: string}
     */
    protected function createLaunchVoucherCampaign(int $amount = 500, int $quantity = 1, array $overrides = []): array
    {
        $generator = app(LaunchVoucherCodeGenerator::class);

        $campaign = LaunchVoucherCampaign::query()->create(array_merge([
            'name' => 'Test Campaign',
            'amount' => $amount,
            'network' => null,
            'distribution_mode' => LaunchVoucherCampaign::DISTRIBUTION_UNIQUE_CODES,
            'generated_count' => $quantity,
            'max_redemptions' => null,
            'expires_at' => now()->addMonth(),
            'active' => true,
            'one_per_phone' => true,
            'one_per_email' => true,
            'one_per_device' => true,
            'reservation_timeout_minutes' => 30,
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

    /**
     * @return array{campaign: LaunchVoucherCampaign, vouchers: array<int, LaunchVoucher>, code: string}
     */
    protected function createSharedLaunchVoucherCampaign(int $amount = 500, int $maxRedemptions = 5, array $overrides = []): array
    {
        $generator = app(LaunchVoucherCodeGenerator::class);
        $code = $generator->generateUnique();

        $campaign = LaunchVoucherCampaign::query()->create(array_merge([
            'name' => 'Shared Test Campaign',
            'amount' => $amount,
            'network' => null,
            'distribution_mode' => LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE,
            'generated_count' => 1,
            'max_redemptions' => $maxRedemptions,
            'expires_at' => now()->addMonth(),
            'active' => true,
            'one_per_phone' => true,
            'one_per_email' => true,
            'one_per_device' => true,
            'reservation_timeout_minutes' => 30,
            'shared_code' => true,
            'created_by' => 'test',
        ], $overrides));

        $voucher = LaunchVoucher::query()->create([
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'code' => $code,
            'code_normalized' => $generator->normalize($code),
            'product_type' => 'airtime',
            'amount' => $campaign->amount,
            'network' => $campaign->network,
            'max_redemptions' => $maxRedemptions,
            'expires_at' => $campaign->expires_at,
            'active' => true,
            'one_per_phone' => $campaign->one_per_phone,
            'one_per_email' => $campaign->one_per_email,
            'one_per_device' => $campaign->one_per_device,
            'created_by' => 'test',
        ]);

        return [
            'campaign' => $campaign,
            'vouchers' => [$voucher],
            'code' => $voucher->code,
        ];
    }

    protected function createCampaignRedemption(
        LaunchVoucher $voucher,
        Transaction $transaction,
        array $overrides = [],
    ): LaunchVoucherRedemption {
        $deviceId = $overrides['device_id'] ?? null;
        $email = $overrides['customer_email'] ?? $transaction->customer_email;

        return LaunchVoucherRedemption::query()->create(array_merge([
            'launch_voucher_id' => $voucher->id,
            'campaign_id' => $voucher->campaign_id,
            'transaction_id' => $transaction->id,
            'customer_phone' => $transaction->customer_phone,
            'customer_phone_normalized' => VoucherIdentityNormalizer::normalizePhone((string) $transaction->customer_phone),
            'customer_email' => $email,
            'customer_email_hash' => VoucherIdentityNormalizer::hashEmail($email),
            'device_id' => $deviceId,
            'device_id_hash' => VoucherIdentityNormalizer::hashDevice($deviceId),
            'status' => LaunchVoucherRedemption::STATUS_REDEEMED,
            'discount_amount' => (int) ($transaction->voucher_discount_amount ?? $voucher->amount),
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ], $overrides));
    }
}
