<?php

namespace Database\Seeders;

use App\Models\LaunchVoucher;
use Illuminate\Database\Seeder;

class LaunchVoucherSeeder extends Seeder
{
    private const LEGACY_CODES = [
        'PAYLITY500',
        'PAYLITY1000',
        'SOFT500',
        'SOFT1000',
        'WELCOME500',
    ];

    public function run(): void
    {
        foreach (self::LEGACY_CODES as $code) {
            LaunchVoucher::query()->updateOrCreate(
                ['code' => $code],
                [
                    'code_normalized' => $code,
                    'name' => 'Legacy predictable voucher (deactivated)',
                    'product_type' => 'airtime',
                    'amount' => str_contains($code, '1000') ? 1000 : 500,
                    'network' => null,
                    'max_redemptions' => 0,
                    'redeemed_count' => 0,
                    'expires_at' => now()->subDay(),
                    'active' => false,
                    'one_per_phone' => true,
                    'one_per_email' => false,
                    'one_per_device' => true,
                    'created_by' => 'system',
                ],
            );
        }
    }
}
