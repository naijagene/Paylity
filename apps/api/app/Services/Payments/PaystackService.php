<?php

namespace App\Services\Payments;

use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

class PaystackService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.paystack.enabled');
    }

    public function hasSecretKey(): bool
    {
        return ! empty(config('services.paystack.secret_key'));
    }

    public function assertConfigured(): void
    {
        if (! $this->hasSecretKey()) {
            throw new PaystackConfigurationException();
        }
    }

    /**
     * @return array{
     *     authorization_url: string,
     *     access_code: string,
     *     reference: string,
     *     raw: array<string, mixed>
     * }
     */
    public function initializeTransaction(Transaction $transaction): array
    {
        $this->assertConfigured();

        $email = $transaction->customer_email ?: $this->fallbackEmail($transaction->customer_phone);
        $amountKobo = $transaction->payable_amount * 100;

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->post($this->endpoint('/transaction/initialize'), [
                'email' => $email,
                'amount' => $amountKobo,
                'reference' => $transaction->reference,
                'callback_url' => config('services.paystack.callback_url'),
                'currency' => 'NGN',
                'metadata' => [
                    'product_type' => $transaction->product_type,
                    'customer_phone' => $transaction->customer_phone,
                    'paylity_reference' => $transaction->reference,
                ],
            ]);

        $payload = $response->json();

        if (! $response->successful() || ! data_get($payload, 'status')) {
            throw new PaystackException(
                (string) data_get($payload, 'message', 'Paystack initialization failed.'),
            );
        }

        return [
            'authorization_url' => (string) data_get($payload, 'data.authorization_url'),
            'access_code' => (string) data_get($payload, 'data.access_code'),
            'reference' => (string) data_get($payload, 'data.reference'),
            'raw' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $reference): array
    {
        $this->assertConfigured();

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->get($this->endpoint('/transaction/verify/'.$reference));

        $payload = $response->json();

        if (! $response->successful() || ! data_get($payload, 'status')) {
            throw new PaystackException(
                (string) data_get($payload, 'message', 'Paystack verification failed.'),
            );
        }

        return is_array($payload) ? $payload : [];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.paystack.base_url'), '/').$path;
    }

    private function fallbackEmail(string $phone): string
    {
        $normalized = preg_replace('/\D/', '', $phone) ?: 'customer';

        return "paylity+{$normalized}@paylity.ng";
    }
}
