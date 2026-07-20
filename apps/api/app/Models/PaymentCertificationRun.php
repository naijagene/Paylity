<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCertificationRun extends Model
{
    public const RESULT_INCOMPLETE = 'INCOMPLETE';

    public const RESULT_CERTIFIED = 'CERTIFIED';

    public const RESULT_CERTIFIED_WITH_WARNINGS = 'CERTIFIED_WITH_WARNINGS';

    public const RESULT_FAILED = 'FAILED';

    protected $fillable = [
        'reference',
        'environment',
        'paystack_mode',
        'provider_mode',
        'intended_product_type',
        'intended_product_amount_kobo',
        'expected_convenience_fee_kobo',
        'expected_gateway_fee_kobo',
        'expected_total_kobo',
        'intended_phone',
        'intended_network',
        'transaction_id',
        'payment_status',
        'fulfillment_status',
        'ledger_status',
        'reconciliation_status',
        'settlement_expectation_status',
        'receipt_status',
        'started_by',
        'started_at',
        'completed_at',
        'result',
        'notes',
        'evidence_json',
    ];

    protected function casts(): array
    {
        return [
            'intended_product_amount_kobo' => 'integer',
            'expected_convenience_fee_kobo' => 'integer',
            'expected_gateway_fee_kobo' => 'integer',
            'expected_total_kobo' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'evidence_json' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isActive(): bool
    {
        return in_array($this->result, [self::RESULT_INCOMPLETE], true) && $this->completed_at === null;
    }
}
