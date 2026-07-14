<?php

namespace Database\Seeders;

use App\Models\LaunchVoucher;
use Illuminate\Database\Seeder;

class LaunchVoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'name' => 'Soft Launch Airtime ₦500',
                'code' => 'PAYLITY500',
                'amount' => 500,
                'max_redemptions' => 500,
                'expires_at' => now()->addMonths(3),
                'created_by' => 'system',
            ],
            [
                'name' => 'Soft Launch Airtime ₦1,000',
                'code' => 'PAYLITY1000',
                'amount' => 1000,
                'max_redemptions' => 250,
                'expires_at' => now()->addMonths(3),
                'created_by' => 'system',
            ],
        ];

        foreach ($vouchers as $voucher) {
            LaunchVoucher::query()->updateOrCreate(
                ['code' => $voucher['code']],
                array_merge($voucher, [
                    'product_type' => 'airtime',
                    'network' => null,
                    'redeemed_count' => 0,
                    'active' => true,
                    'one_per_phone' => true,
                    'one_per_email' => false,
                    'one_per_device' => true,
                ]),
            );
        }
    }
}
