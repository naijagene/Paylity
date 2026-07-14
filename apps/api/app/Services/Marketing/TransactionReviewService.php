<?php

namespace App\Services\Marketing;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionReview;
use App\Services\TransactionEventService;

class TransactionReviewService
{
    public function __construct(
        private readonly MarketingEventService $marketingEventService,
        private readonly TransactionEventService $transactionEventService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(Transaction $transaction, int $rating, ?string $comment = null): array
    {
        if ($transaction->status !== TransactionStatus::FULFILLED) {
            throw new \InvalidArgumentException('Reviews are only available after successful fulfillment.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        }

        $review = TransactionReview::query()->updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'reference' => $transaction->reference,
                'rating' => $rating,
                'comment' => $comment,
                'customer_phone' => $transaction->customer_phone,
            ],
        );

        $this->transactionEventService->record(
            $transaction,
            'review.submitted',
            'Customer submitted a transaction review.',
            'customer',
            ['rating' => $rating],
        );

        $this->marketingEventService->track(
            MarketingEventService::TYPE_REVIEW_SUBMITTED,
            $transaction,
            $transaction->launch_voucher_id,
            ['rating' => $rating],
        );

        return [
            'reference' => $transaction->reference,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'submitted_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{count: int, average_rating: float|null, distribution: array<int, int>}
     */
    public function aggregateStats(): array
    {
        $reviews = TransactionReview::query()->get(['rating']);
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($reviews as $review) {
            $distribution[(int) $review->rating] = ($distribution[(int) $review->rating] ?? 0) + 1;
        }

        return [
            'count' => $reviews->count(),
            'average_rating' => $reviews->count() > 0
                ? round($reviews->avg('rating'), 2)
                : null,
            'distribution' => $distribution,
        ];
    }
}
