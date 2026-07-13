<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('category', 32);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 64)->default('transaction');
            $table->string('source_id', 64)->nullable();
            $table->string('transaction_reference', 64)->nullable();
            $table->string('event_type', 64);
            $table->string('idempotency_key', 128)->unique();
            $table->string('description');
            $table->string('status', 32)->default('posted');
            $table->json('metadata')->nullable();
            $table->string('operator_id', 64)->nullable();
            $table->timestamp('posted_at');
            $table->foreignId('reversed_by_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['transaction_id', 'event_type']);
            $table->index(['posted_at', 'event_type']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('ledger_accounts');
            $table->string('entry_type', 16);
            $table->unsignedBigInteger('amount_kobo');
            $table->char('currency', 3)->default('NGN');
            $table->timestamps();

            $table->index(['account_id', 'entry_type']);
        });

        Schema::create('transaction_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('provider_cost_kobo')->nullable();
            $table->string('provider_cost_source', 32)->nullable();
            $table->string('provider_cost_status', 32)->nullable();
            $table->bigInteger('gross_margin_kobo')->nullable();
            $table->unsignedBigInteger('gateway_fee_expected_kobo')->nullable();
            $table->unsignedBigInteger('gateway_fee_actual_kobo')->nullable();
            $table->string('settlement_status', 32)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->date('settlement_date');
            $table->unsignedBigInteger('expected_amount_kobo')->default(0);
            $table->unsignedBigInteger('actual_amount_kobo')->nullable();
            $table->bigInteger('difference_kobo')->default(0);
            $table->string('status', 32)->default('open');
            $table->json('metadata')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'settlement_date']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_reference', 64)->nullable();
            $table->unsignedBigInteger('expected_amount_kobo')->default(0);
            $table->unsignedBigInteger('actual_amount_kobo')->nullable();
            $table->bigInteger('difference_kobo')->default(0);
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['settlement_batch_id', 'status']);
        });

        Schema::create('daily_financial_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->json('metrics');
            $table->string('status', 32)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_financial_snapshots');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('transaction_financials');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
