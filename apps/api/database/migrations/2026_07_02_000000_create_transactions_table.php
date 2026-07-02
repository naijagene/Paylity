<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('product_type', 32);
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('product_amount');
            $table->unsignedInteger('convenience_fee');
            $table->unsignedInteger('gateway_fee')->default(0);
            $table->unsignedInteger('payable_amount');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 32);
            $table->string('payment_provider')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_authorization_url')->nullable();
            $table->string('fulfillment_provider')->nullable();
            $table->string('fulfillment_reference')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('verified_phone')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index(['customer_phone', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['product_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
