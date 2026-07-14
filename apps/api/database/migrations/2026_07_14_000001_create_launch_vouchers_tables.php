<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('product_type')->default('airtime');
            $table->unsignedInteger('amount');
            $table->string('network')->nullable();
            $table->unsignedInteger('max_redemptions');
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('one_per_phone')->default(true);
            $table->boolean('one_per_email')->default(false);
            $table->boolean('one_per_device')->default(true);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('launch_voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('launch_voucher_id')->constrained('launch_vouchers')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('device_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['launch_voucher_id', 'customer_phone']);
            $table->index(['launch_voucher_id', 'device_id']);
            $table->index(['launch_voucher_id', 'customer_email']);
        });

        Schema::create('transaction_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('reference')->index();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('customer_phone')->nullable();
            $table->timestamps();

            $table->unique('transaction_id');
        });

        Schema::create('marketing_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('reference')->nullable()->index();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('launch_voucher_id')->nullable()->constrained('launch_vouchers')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('actor')->default('customer');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_events');
        Schema::dropIfExists('transaction_reviews');
        Schema::dropIfExists('launch_voucher_redemptions');
        Schema::dropIfExists('launch_vouchers');
    }
};
