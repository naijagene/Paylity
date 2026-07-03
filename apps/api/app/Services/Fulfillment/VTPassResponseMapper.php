<?php

namespace App\Services\Fulfillment;

class VTPassResponseMapper
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRYABLE = 'retryable';

    public const STATUS_UNKNOWN = 'unknown';

    /** @var array<string, string> */
    private const CODE_STATUS_MAP = [
        '000' => self::STATUS_SUCCESS,
        '099' => self::STATUS_PENDING,
        '001' => self::STATUS_PENDING,
        '016' => self::STATUS_FAILED,
        '017' => self::STATUS_FAILED,
        '018' => self::STATUS_FAILED,
        '019' => self::STATUS_FAILED,
        '020' => self::STATUS_FAILED,
        '021' => self::STATUS_FAILED,
        '022' => self::STATUS_FAILED,
        '023' => self::STATUS_FAILED,
        '024' => self::STATUS_FAILED,
        '025' => self::STATUS_FAILED,
        '026' => self::STATUS_FAILED,
        '027' => self::STATUS_FAILED,
        '028' => self::STATUS_FAILED,
        '030' => self::STATUS_RETRYABLE,
        '031' => self::STATUS_RETRYABLE,
        '032' => self::STATUS_RETRYABLE,
        '034' => self::STATUS_RETRYABLE,
        '035' => self::STATUS_RETRYABLE,
        '040' => self::STATUS_RETRYABLE,
        '041' => self::STATUS_FAILED,
        '042' => self::STATUS_FAILED,
        '043' => self::STATUS_FAILED,
        '044' => self::STATUS_FAILED,
        '045' => self::STATUS_FAILED,
        '046' => self::STATUS_FAILED,
        '047' => self::STATUS_FAILED,
        '048' => self::STATUS_FAILED,
        '049' => self::STATUS_FAILED,
        '050' => self::STATUS_FAILED,
    ];

    /**
     * @param  array<string, mixed>  $response
     * @return array{
     *     status: string,
     *     code: string|null,
     *     message: string,
     *     retryable: bool,
     *     transaction_status: string|null
     * }
     */
    public function map(array $response): array
    {
        $code = $this->normalizeCode(data_get($response, 'code'));
        $transactionStatus = strtolower((string) data_get($response, 'content.transactions.status', ''));
        $message = $this->resolveMessage($response);

        if ($code === '000') {
            return $this->result(self::STATUS_SUCCESS, $code, $message, false, $transactionStatus);
        }

        if ($transactionStatus !== '') {
            if (in_array($transactionStatus, ['delivered', 'successful', 'success'], true)) {
                return $this->result(self::STATUS_SUCCESS, $code, $message, false, $transactionStatus);
            }

            if (in_array($transactionStatus, ['pending', 'processing', 'initiated'], true)) {
                return $this->result(self::STATUS_PENDING, $code, $message, false, $transactionStatus);
            }
        }

        if ($code !== null && isset(self::CODE_STATUS_MAP[$code])) {
            $status = self::CODE_STATUS_MAP[$code];

            return $this->result(
                $status,
                $code,
                $message,
                $status === self::STATUS_RETRYABLE,
                $transactionStatus ?: null,
            );
        }

        return $this->result(self::STATUS_UNKNOWN, $code, $message, false, $transactionStatus ?: null);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function isSuccessful(array $response): bool
    {
        return $this->map($response)['status'] === self::STATUS_SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function failureReason(array $response): string
    {
        return $this->resolveMessage($response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveMessage(array $response): string
    {
        return (string) (
            data_get($response, 'response_description')
            ?? data_get($response, 'content.transactions.product_name')
            ?? data_get($response, 'message')
            ?? 'VTPass request failed.'
        );
    }

    private function normalizeCode(mixed $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return str_pad((string) $code, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *     status: string,
     *     code: string|null,
     *     message: string,
     *     retryable: bool,
     *     transaction_status: string|null
     * }
     */
    private function result(
        string $status,
        ?string $code,
        string $message,
        bool $retryable,
        ?string $transactionStatus,
    ): array {
        return [
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
            'transaction_status' => $transactionStatus,
        ];
    }
}
