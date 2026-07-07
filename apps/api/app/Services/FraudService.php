<?php

namespace App\Services;

use App\Exceptions\FraudCheckException;
use App\Services\Platform\PurchasePolicyContext;
use App\Services\Platform\PurchasePolicyService;

/**
 * @deprecated Use PurchasePolicyService directly. Kept for backward compatibility.
 */
class FraudService
{
    public function __construct(
        private readonly PurchasePolicyService $purchasePolicyService,
    ) {
    }

    /**
     * @throws FraudCheckException
     */
    public function assertCanInitialize(
        int $productAmount,
        string $customerPhone,
        ?string $ipAddress,
        bool $verifiedPhone = false,
        bool $registeredCustomer = false,
    ): void {
        $this->purchasePolicyService->assertCanInitialize(
            new PurchasePolicyContext(
                productAmount: $productAmount,
                customerPhone: $customerPhone,
                ipAddress: $ipAddress,
                verifiedPhone: $verifiedPhone,
                registeredCustomer: $registeredCustomer,
            ),
        );
    }
}
