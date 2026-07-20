<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_certification_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->index();
            $table->string('environment');
            $table->string('paystack_mode');
            $table->string('provider_mode')->nullable();
            $table->string('intended_product_type')->default('airtime');
            $table->unsignedInteger('intended_product_amount_kobo')->default(10000);
            $table->unsignedInteger('expected_convenience_fee_kobo')->default(0);
            $table->unsignedInteger('expected_gateway_fee_kobo')->default(0);
            $table->unsignedInteger('expected_total_kobo')->default(0);
            $table->string('intended_phone')->nullable();
            $table->string('intended_network')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->string('payment_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('ledger_status')->nullable();
            $table->string('reconciliation_status')->nullable();
            $table->string('settlement_expectation_status')->nullable();
            $table->string('receipt_status')->nullable();
            $table->string('started_by')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('result')->default('INCOMPLETE');
            $table->text('notes')->nullable();
            $table->json('evidence_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_certification_runs');
    }
};
