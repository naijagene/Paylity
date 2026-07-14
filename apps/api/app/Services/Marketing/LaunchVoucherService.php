<?php

namespace App\Services\Marketing;

use App\Enums\TransactionStatus;
use App\Exceptions\LaunchVoucherException;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\Transaction;
use App\Services\FeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LaunchVoucherService
{
    public function __construct(
        private readonly FeeService $feeService,
        private readonly MarketingEventService $marketingEventService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validateForCheckout(array $input, bool $trackEvent = true): array
    {
        $voucher = $this->resolveVoucher((string) ($input['code'] ?? ''));
        $productType = (string) ($input['product_type'] ?? '');
        $productAmount = (int) ($input['product_amount'] ?? 0);
        $network = (string) ($input['network'] ?? '');
        $phone = (string) ($input['customer_phone'] ?? '');
        $email = (string) ($input['customer_email'] ?? '');
        $deviceId = (string) ($input['device_id'] ?? '');

        $this->assertEligibility($voucher, $productType, $productAmount, $network, $phone, $email, $deviceId);

        $discountAmount = min($voucher->amount, $productAmount);
        $quote = $this->feeService->quote($productType, $productAmount, $discountAmount);

        if ($quote['payable_amount'] <= 0) {
            throw new LaunchVoucherException('Checkout total must be greater than zero.', 'VOUCHER_ZERO_PAYABLE');
        }

        if ($trackEvent) {
            $this->marketingEventService->track(
                MarketingEventService::TYPE_VOUCHER_VALIDATED,
                launchVoucherId: $voucher->id,
                metadata: [
                    'code' => $voucher->code,
                    'product_type' => $productType,
                    'product_amount' => $productAmount,
                    'discount_amount' => $discountAmount,
                ],
            );
        }

        return [
            'valid' => true,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'voucher_amount' => $voucher->amount,
            'discount_amount' => $discountAmount,
            'product_amount' => $productAmount,
            'net_product_amount' => $quote['net_product_amount'],
            'pre_gateway_charge' => $quote['pre_gateway_charge'],
            'convenience_fee' => $quote['convenience_fee'],
            'gateway_fee' => $quote['gateway_fee'],
            'payable_amount' => $quote['payable_amount'],
            'remaining_redemptions' => $voucher->remainingRedemptions(),
        ];
    }

    /**
     * @return array{voucher: LaunchVoucher, discount_amount: int, redemption: LaunchVoucherRedemption}
     */
    public function reserveForTransaction(
        Transaction $transaction,
        string $code,
        ?string $deviceId = null,
        ?int $discountAmount = null,
    ): array {
        $voucher = $this->resolveVoucher($code);

        if ($discountAmount === null) {
            $network = (string) data_get($transaction->request_payload, 'network', '');
            $validation = $this->validateForCheckout([
                'code' => $code,
                'product_type' => $transaction->product_type,
                'product_amount' => $transaction->product_amount,
                'network' => $network,
                'customer_phone' => $transaction->customer_phone,
                'customer_email' => $transaction->customer_email,
                'device_id' => $deviceId,
            ], trackEvent: false);
            $discountAmount = (int) $validation['discount_amount'];
        }

        $redemption = LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $voucher->id,
            'transaction_id' => $transaction->id,
            'customer_phone' => $transaction->customer_phone,
            'customer_email' => $transaction->customer_email,
            'device_id' => $deviceId,
            'status' => LaunchVoucherRedemption::STATUS_PENDING,
            'discount_amount' => $discountAmount,
        ]);

        return [
            'voucher' => $voucher,
            'discount_amount' => $discountAmount,
            'redemption' => $redemption,
        ];
    }

    public function completeRedemption(Transaction $transaction): void
    {
        if ((int) ($transaction->voucher_discount_amount ?? 0) <= 0 || ! $transaction->launch_voucher_id) {
            return;
        }

        DB::transaction(function () use ($transaction): void {
            $voucher = LaunchVoucher::query()->lockForUpdate()->find($transaction->launch_voucher_id);

            if (! $voucher) {
                return;
            }

            $redemption = LaunchVoucherRedemption::query()
                ->where('transaction_id', $transaction->id)
                ->where('status', LaunchVoucherRedemption::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                return;
            }

            if ($voucher->redeemed_count >= $voucher->max_redemptions) {
                return;
            }

            $redemption->update([
                'status' => LaunchVoucherRedemption::STATUS_COMPLETED,
                'redeemed_at' => now(),
            ]);

            $voucher->increment('redeemed_count');

            $this->marketingEventService->track(
                MarketingEventService::TYPE_VOUCHER_REDEEMED,
                $transaction,
                $voucher->id,
                [
                    'code' => $voucher->code,
                    'discount_amount' => $transaction->voucher_discount_amount,
                ],
            );
        });
    }

    private function resolveVoucher(string $code): LaunchVoucher
    {
        $normalized = Str::upper(trim($code));

        if ($normalized === '') {
            throw new LaunchVoucherException('Voucher code is required.', 'VOUCHER_REQUIRED');
        }

        $voucher = LaunchVoucher::query()->where('code', $normalized)->first();

        if (! $voucher) {
            throw new LaunchVoucherException('Voucher code is invalid.', 'VOUCHER_NOT_FOUND');
        }

        return $voucher;
    }

    private function assertEligibility(
        LaunchVoucher $voucher,
        string $productType,
        int $productAmount,
        string $network,
        string $phone,
        string $email,
        string $deviceId,
    ): void {
        if (! $voucher->active) {
            throw new LaunchVoucherException('This voucher is not active.', 'VOUCHER_INACTIVE');
        }

        if ($voucher->isExpired()) {
            throw new LaunchVoucherException('This voucher has expired.', 'VOUCHER_EXPIRED');
        }

        if ($voucher->remainingRedemptions() <= 0) {
            throw new LaunchVoucherException('This voucher has no remaining redemptions.', 'VOUCHER_EXHAUSTED');
        }

        if ($voucher->product_type !== $productType) {
            throw new LaunchVoucherException('This voucher is only valid for '.$voucher->product_type.'.', 'VOUCHER_PRODUCT_MISMATCH');
        }

        if ($productType !== 'airtime') {
            throw new LaunchVoucherException('Launch vouchers are currently available for airtime only.', 'VOUCHER_AIRTIME_ONLY');
        }

        if ($voucher->network && $network !== '' && strtoupper($network) !== strtoupper((string) $voucher->network)) {
            throw new LaunchVoucherException('This voucher is not valid for the selected network.', 'VOUCHER_NETWORK_MISMATCH');
        }

        if ($productAmount <= 0) {
            throw new LaunchVoucherException('Enter a product amount before applying a voucher.', 'VOUCHER_AMOUNT_REQUIRED');
        }

        if ($voucher->one_per_phone && $phone !== '' && $this->hasRestrictionConflict($voucher->id, 'customer_phone', $phone)) {
            throw new LaunchVoucherException('This voucher has already been used for this phone number.', 'VOUCHER_PHONE_USED');
        }

        if ($voucher->one_per_email && $email !== '' && $this->hasRestrictionConflict($voucher->id, 'customer_email', $email)) {
            throw new LaunchVoucherException('This voucher has already been used for this email address.', 'VOUCHER_EMAIL_USED');
        }

        if ($voucher->one_per_device && $deviceId !== '' && $this->hasRestrictionConflict($voucher->id, 'device_id', $deviceId)) {
            throw new LaunchVoucherException('This voucher has already been used on this device.', 'VOUCHER_DEVICE_USED');
        }
    }

    private function hasRestrictionConflict(int $voucherId, string $column, string $value): bool
    {
        $activeStatuses = [
            LaunchVoucherRedemption::STATUS_COMPLETED,
            LaunchVoucherRedemption::STATUS_PENDING,
        ];

        return LaunchVoucherRedemption::query()
            ->where('launch_voucher_id', $voucherId)
            ->where($column, $value)
            ->whereIn('status', $activeStatuses)
            ->whereHas('transaction', function ($query): void {
                $query->whereNotIn('status', [
                    TransactionStatus::PAYMENT_FAILED,
                    TransactionStatus::FAILED,
                    TransactionStatus::CANCELLED,
                ]);
            })
            ->exists();
    }
}
