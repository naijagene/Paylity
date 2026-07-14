<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitTransactionReviewRequest;
use App\Models\Transaction;
use App\Services\Marketing\MarketingEventService;
use App\Services\Marketing\TransactionReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionReviewController extends Controller
{
    public function __construct(
        private readonly TransactionReviewService $transactionReviewService,
        private readonly MarketingEventService $marketingEventService,
    ) {
    }

    public function store(SubmitTransactionReviewRequest $request, string $reference): JsonResponse
    {
        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        try {
            $review = $this->transactionReviewService->submit(
                $transaction,
                (int) $request->input('rating'),
                $request->input('comment'),
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => 'REVIEW_NOT_ALLOWED'],
                status: 422,
            );
        }

        return ApiResponse::success(
            data: $review,
            message: 'Review submitted successfully.',
            status: 201,
        );
    }

    public function trackShare(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:whatsapp,facebook,telegram,x,copy_link'],
        ]);

        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        $this->marketingEventService->track(
            MarketingEventService::TYPE_SHARE_INITIATED,
            $transaction,
            $transaction->launch_voucher_id,
            ['channel' => $validated['channel']],
        );

        return ApiResponse::success(
            data: ['tracked' => true, 'channel' => $validated['channel']],
            message: 'Share event recorded.',
        );
    }
}
