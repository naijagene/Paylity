<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\OpsNote;
use App\Models\Transaction;
use App\Services\TransactionEventService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsNoteController extends Controller
{
    public function __construct(
        private readonly TransactionEventService $transactionEventService,
    ) {
    }

    public function index(string $reference): JsonResponse
    {
        $transaction = $this->findTransaction($reference);

        if (! $transaction) {
            return ApiResponse::error(
                message: 'Transaction not found.',
                errors: ['code' => 'TRANSACTION_NOT_FOUND'],
                status: 404,
            );
        }

        $notes = OpsNote::query()
            ->where('transaction_id', $transaction->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OpsNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author,
                'created_at' => $note->created_at?->toIso8601String(),
            ])
            ->all();

        return ApiResponse::success(
            data: $notes,
            message: 'Operations notes retrieved successfully.',
        );
    }

    public function store(Request $request, string $reference): JsonResponse
    {
        $transaction = $this->findTransaction($reference);

        if (! $transaction) {
            return ApiResponse::error(
                message: 'Transaction not found.',
                errors: ['code' => 'TRANSACTION_NOT_FOUND'],
                status: 404,
            );
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'author' => ['nullable', 'string', 'max:64'],
        ]);

        $note = OpsNote::query()->create([
            'transaction_id' => $transaction->id,
            'body' => $validated['body'],
            'author' => $validated['author'] ?? 'operator',
        ]);

        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_OPS_NOTE,
            'Operator note added.',
            'operator',
            ['note_id' => $note->id],
        );

        return ApiResponse::success(
            data: [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author,
                'created_at' => $note->created_at?->toIso8601String(),
            ],
            message: 'Operations note created successfully.',
            status: 201,
        );
    }

    private function findTransaction(string $reference): ?Transaction
    {
        return Transaction::query()->where('reference', $reference)->first();
    }
}
