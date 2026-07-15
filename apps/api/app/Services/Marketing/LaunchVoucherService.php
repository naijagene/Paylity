<?php

namespace App\Services\Marketing;

use App\Enums\TransactionStatus;
use App\Exceptions\LaunchVoucherException;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Models\Transaction;
use App\Services\FeeService;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use App\Support\Marketing\VoucherIdentityNormalizer;
use Illuminate\Support\Facades\DB;

class LaunchVoucherService
{
    public function __construct(
        private readonly FeeService $feeService,
        private readonly MarketingEventService $marketingEventService,
        private readonly LaunchVoucherCodeGenerator $codeGenerator,
        private readonly LaunchVoucherCampaignCapacityService $campaignCapacityService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validateForCheckout(array $input, bool $trackEvent = true): array
    {
        $voucher = $this->resolveVoucher((string) ($input['code'] ?? ''));

        return $this->evaluateEligibility($voucher, $input, reserve: false, trackEvent: $trackEvent);
    }

    /**
     * @return array{voucher: LaunchVoucher, discount_amount: int, redemption: LaunchVoucherRedemption, quote: array<string, mixed>}
     */
    public function reserveForTransaction(
        Transaction $transaction,
        string $code,
        ?string $deviceId = null,
    ): array {
        return DB::transaction(function () use ($transaction, $code, $deviceId): array {
            $normalized = $this->codeGenerator->normalize($code);

            $voucher = LaunchVoucher::query()
                ->where('code_normalized', $normalized)
                ->lockForUpdate()
                ->first();

            if (! $voucher) {
                throw new LaunchVoucherException('Voucher code is invalid.', 'VOUCHER_NOT_FOUND');
            }

            $input = [
                'code' => $code,
                'product_type' => $transaction->product_type,
                'product_amount' => (int) $transaction->product_amount,
                'network' => (string) data_get($transaction->request_payload, 'network', ''),
                'customer_phone' => (string) $transaction->customer_phone,
                'customer_email' => (string) ($transaction->customer_email ?? ''),
                'device_id' => (string) ($deviceId ?? ''),
            ];

            $evaluation = $this->evaluateEligibility($voucher, $input, reserve: true, trackEvent: false);
            $quote = $evaluation;
            $discountAmount = (int) $evaluation['discount_amount'];

            $identity = $this->normalizeIdentity(
                (string) $transaction->customer_phone,
                (string) ($transaction->customer_email ?? ''),
                (string) ($deviceId ?? ''),
            );

            $redemption = LaunchVoucherRedemption::query()->create([
                'launch_voucher_id' => $voucher->id,
                'campaign_id' => $voucher->campaign_id,
                'transaction_id' => $transaction->id,
                'customer_phone' => $transaction->customer_phone,
                'customer_phone_normalized' => $identity['phone_normalized'],
                'customer_email' => $transaction->customer_email,
                'customer_email_hash' => $identity['email_hash'],
                'device_id' => $deviceId,
                'device_id_hash' => $identity['device_hash'],
                'status' => LaunchVoucherRedemption::STATUS_RESERVED,
                'discount_amount' => $discountAmount,
                'reserved_at' => now(),
            ]);

            $this->marketingEventService->track(
                MarketingEventService::TYPE_VOUCHER_RESERVED,
                $transaction,
                $voucher->id,
                [
                    'campaign_id' => $voucher->campaign_id,
                    'discount_amount' => $discountAmount,
                ],
            );

            return [
                'voucher' => $voucher->fresh(),
                'discount_amount' => $discountAmount,
                'redemption' => $redemption,
                'quote' => [
                    'convenience_fee' => $evaluation['convenience_fee'],
                    'gateway_fee' => $evaluation['gateway_fee'],
                    'payable_amount' => $evaluation['payable_amount'],
                    'net_product_amount' => $evaluation['net_product_amount'],
                    'pre_gateway_charge' => $evaluation['pre_gateway_charge'],
                ],
            ];
        });
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
                ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                return;
            }

            if ($voucher->campaign_id) {
                LaunchVoucherCampaign::query()->lockForUpdate()->find($voucher->campaign_id);
            }

            if ($voucher->redeemed_count >= $voucher->max_redemptions) {
                return;
            }

            $redemption->update([
                'status' => LaunchVoucherRedemption::STATUS_REDEEMED,
                'redeemed_at' => now(),
            ]);

            $voucher->increment('redeemed_count');

            if ($voucher->campaign_id) {
                LaunchVoucherCampaign::query()
                    ->where('id', $voucher->campaign_id)
                    ->increment('redeemed_count');
            }

            $this->marketingEventService->track(
                MarketingEventService::TYPE_VOUCHER_REDEEMED,
                $transaction,
                $voucher->id,
                [
                    'campaign_id' => $voucher->campaign_id,
                    'discount_amount' => $transaction->voucher_discount_amount,
                ],
            );
        });
    }

    public function releaseReservation(Transaction $transaction, string $reason = 'released'): void
    {
        if (! $transaction->launch_voucher_id) {
            return;
        }

        DB::transaction(function () use ($transaction, $reason): void {
            $redemption = LaunchVoucherRedemption::query()
                ->where('transaction_id', $transaction->id)
                ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                return;
            }

            $redemption->update([
                'status' => LaunchVoucherRedemption::STATUS_RELEASED,
                'released_at' => now(),
            ]);

            $this->marketingEventService->track(
                MarketingEventService::TYPE_VOUCHER_RELEASED,
                $transaction,
                $transaction->launch_voucher_id,
                ['reason' => $reason],
            );
        });
    }

    public function maskCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return $this->codeGenerator->mask($code);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function evaluateEligibility(
        LaunchVoucher $voucher,
        array $input,
        bool $reserve,
        bool $trackEvent,
    ): array {
        $productType = (string) ($input['product_type'] ?? '');
        $productAmount = (int) ($input['product_amount'] ?? 0);
        $network = (string) ($input['network'] ?? '');
        $phone = (string) ($input['customer_phone'] ?? '');
        $email = (string) ($input['customer_email'] ?? '');
        $deviceId = (string) ($input['device_id'] ?? '');

        $campaign = null;
        if ($voucher->campaign_id) {
            $campaign = $reserve
                ? LaunchVoucherCampaign::query()->lockForUpdate()->find($voucher->campaign_id)
                : LaunchVoucherCampaign::query()->find($voucher->campaign_id);
        }

        $this->assertEligibility($voucher, $campaign, $productType, $productAmount, $network, $phone, $email, $deviceId, $reserve);

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
                    'campaign_id' => $voucher->campaign_id,
                    'product_type' => $productType,
                    'product_amount' => $productAmount,
                    'discount_amount' => $discountAmount,
                ],
            );
        }

        return $this->presentQuote($voucher, $productAmount, $discountAmount, $quote);
    }

    private function resolveVoucher(string $code): LaunchVoucher
    {
        $normalized = $this->codeGenerator->normalize($code);

        if ($normalized === '') {
            throw new LaunchVoucherException('Voucher code is required.', 'VOUCHER_REQUIRED');
        }

        $voucher = LaunchVoucher::query()->where('code_normalized', $normalized)->first();

        if (! $voucher) {
            throw new LaunchVoucherException('Voucher code is invalid.', 'VOUCHER_NOT_FOUND');
        }

        return $voucher;
    }

    private function assertEligibility(
        LaunchVoucher $voucher,
        ?LaunchVoucherCampaign $campaign,
        string $productType,
        int $productAmount,
        string $network,
        string $phone,
        string $email,
        string $deviceId,
        bool $reserve,
    ): void {
        if (! $voucher->active) {
            throw new LaunchVoucherException('This voucher is not active.', 'VOUCHER_INACTIVE');
        }

        if ($campaign && ! $campaign->active) {
            throw new LaunchVoucherException('This voucher campaign is not active.', 'VOUCHER_INACTIVE');
        }

        if ($voucher->isExpired()) {
            throw new LaunchVoucherException('This voucher has expired.', 'VOUCHER_EXPIRED');
        }

        if ($voucher->product_type !== $productType) {
            throw new LaunchVoucherException('This voucher is only valid for '.$voucher->product_type.'.', 'VOUCHER_PRODUCT_MISMATCH');
        }

        if ($productType !== 'airtime') {
            throw new LaunchVoucherException('Launch vouchers are currently available for airtime only.', 'VOUCHER_AIRTIME_ONLY');
        }

        if (! in_array($voucher->amount, LaunchVoucher::ALLOWED_AMOUNTS, true)) {
            throw new LaunchVoucherException('This voucher amount is not supported.', 'VOUCHER_AMOUNT_UNSUPPORTED');
        }

        $allowedNetwork = $voucher->network ?? $campaign?->network;
        if ($allowedNetwork && $network !== '' && strtoupper($network) !== strtoupper((string) $allowedNetwork)) {
            throw new LaunchVoucherException('This voucher is not valid for the selected network.', 'VOUCHER_NETWORK_MISMATCH');
        }

        if ($productAmount <= 0) {
            throw new LaunchVoucherException('Enter a product amount before applying a voucher.', 'VOUCHER_AMOUNT_REQUIRED');
        }

        $identity = $this->normalizeIdentity($phone, $email, $deviceId);
        $restrictions = $this->restrictionSettings($voucher, $campaign);

        if ($restrictions['one_per_phone'] && $identity['phone_normalized'] !== '' && $this->hasCampaignIdentityConflict($voucher, 'customer_phone_normalized', $identity['phone_normalized'])) {
            $this->trackBlocked($voucher, 'phone');
            throw new LaunchVoucherException('This voucher has already been used for this phone number.', 'VOUCHER_PHONE_USED');
        }

        if ($restrictions['one_per_email'] && $identity['email_hash'] !== null && $this->hasCampaignIdentityConflict($voucher, 'customer_email_hash', $identity['email_hash'])) {
            $this->trackBlocked($voucher, 'email');
            throw new LaunchVoucherException('This voucher has already been used for this email address.', 'VOUCHER_EMAIL_USED');
        }

        if ($restrictions['one_per_device'] && $identity['device_hash'] !== null && $this->hasCampaignIdentityConflict($voucher, 'device_id_hash', $identity['device_hash'], $deviceId)) {
            $this->trackBlocked($voucher, 'device');
            throw new LaunchVoucherException('This voucher has already been used on this device.', 'VOUCHER_DEVICE_USED');
        }

        if ($this->isUniqueCodeConsumed($voucher)) {
            throw new LaunchVoucherException('This voucher code has already been used.', 'VOUCHER_CODE_USED');
        }

        if (! $campaign?->isSharedCode() && $voucher->remainingRedemptions() <= 0) {
            throw new LaunchVoucherException('This voucher has no remaining redemptions.', 'VOUCHER_EXHAUSTED');
        }

        if ($reserve) {
            if ($campaign) {
                $this->campaignCapacityService->assertCapacityAvailable($campaign);
            } elseif ($voucher->remainingRedemptions() <= 0) {
                throw new LaunchVoucherException('This voucher has no remaining redemptions.', 'VOUCHER_EXHAUSTED');
            }
        } elseif ($campaign && $this->campaignCapacityService->remainingCapacity($campaign) <= 0) {
            throw new LaunchVoucherException('This voucher campaign has no remaining capacity.', 'VOUCHER_CAMPAIGN_EXHAUSTED');
        } elseif (! $campaign && $voucher->remainingRedemptions() <= 0) {
            throw new LaunchVoucherException('This voucher has no remaining redemptions.', 'VOUCHER_EXHAUSTED');
        }
    }

    private function isUniqueCodeConsumed(LaunchVoucher $voucher): bool
    {
        if ($voucher->campaign?->isSharedCode()) {
            return false;
        }

        if ($voucher->max_redemptions !== 1) {
            return false;
        }

        return $voucher->redemptions()
            ->whereIn('status', [
                LaunchVoucherRedemption::STATUS_RESERVED,
                LaunchVoucherRedemption::STATUS_REDEEMED,
            ])
            ->whereHas('transaction', function ($query): void {
                $query->whereNotIn('status', [
                    TransactionStatus::PAYMENT_FAILED,
                    TransactionStatus::FAILED,
                    TransactionStatus::CANCELLED,
                ]);
            })
            ->exists();
    }

    private function hasCampaignIdentityConflict(LaunchVoucher $voucher, string $column, string $value, ?string $rawDeviceId = null): bool
    {
        $query = LaunchVoucherRedemption::query()
            ->whereIn('status', [
                LaunchVoucherRedemption::STATUS_RESERVED,
                LaunchVoucherRedemption::STATUS_REDEEMED,
            ])
            ->whereHas('transaction', function ($inner): void {
                $inner->whereNotIn('status', [
                    TransactionStatus::PAYMENT_FAILED,
                    TransactionStatus::FAILED,
                    TransactionStatus::CANCELLED,
                ]);
            });

        if ($voucher->campaign_id) {
            $query->where('campaign_id', $voucher->campaign_id);
        } else {
            $query->where('launch_voucher_id', $voucher->id);
        }

        if ($column === 'customer_phone_normalized') {
            $query->where(function ($inner) use ($value): void {
                $inner->where('customer_phone_normalized', $value)
                    ->orWhere('customer_phone', $value);
            });
        } elseif ($column === 'device_id_hash') {
            $query->where(function ($inner) use ($value, $rawDeviceId): void {
                $inner->where('device_id_hash', $value);

                if ($rawDeviceId !== null && $rawDeviceId !== '') {
                    $inner->orWhere('device_id', $rawDeviceId);
                }
            });
        } else {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * @return array{one_per_phone: bool, one_per_email: bool, one_per_device: bool}
     */
    private function restrictionSettings(LaunchVoucher $voucher, ?LaunchVoucherCampaign $campaign): array
    {
        return [
            'one_per_phone' => (bool) ($campaign?->one_per_phone ?? $voucher->one_per_phone),
            'one_per_email' => (bool) ($campaign?->one_per_email ?? $voucher->one_per_email),
            'one_per_device' => (bool) ($campaign?->one_per_device ?? $voucher->one_per_device),
        ];
    }

    /**
     * @return array{phone_normalized: string, email_hash: ?string, device_hash: ?string}
     */
    private function normalizeIdentity(string $phone, string $email, string $deviceId): array
    {
        return [
            'phone_normalized' => VoucherIdentityNormalizer::normalizePhone($phone),
            'email_hash' => VoucherIdentityNormalizer::hashEmail($email),
            'device_hash' => VoucherIdentityNormalizer::hashDevice($deviceId),
        ];
    }

    private function trackBlocked(LaunchVoucher $voucher, string $reason): void
    {
        $this->marketingEventService->track(
            MarketingEventService::TYPE_VOUCHER_BLOCKED,
            launchVoucherId: $voucher->id,
            metadata: [
                'campaign_id' => $voucher->campaign_id,
                'reason' => $reason,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function presentQuote(LaunchVoucher $voucher, int $productAmount, int $discountAmount, array $quote): array
    {
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
}
