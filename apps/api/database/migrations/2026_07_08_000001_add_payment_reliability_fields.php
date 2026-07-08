<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('needs_manual_review')->default(false)->after('failure_reason');
            $table->string('manual_review_reason')->nullable()->after('needs_manual_review');
            $table->timestamp('manual_review_at')->nullable()->after('manual_review_reason');
            $table->unsignedSmallInteger('fulfillment_retry_count')->default(0)->after('manual_review_at');
            $table->timestamp('next_fulfillment_retry_at')->nullable()->after('fulfillment_retry_count');

            $table->index(['needs_manual_review', 'updated_at']);
            $table->index(['next_fulfillment_retry_at', 'status']);
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('failure_reason')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('failure_reason');

            $table->index(['provider', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['needs_manual_review', 'updated_at']);
            $table->dropIndex(['next_fulfillment_retry_at', 'status']);
            $table->dropColumn([
                'needs_manual_review',
                'manual_review_reason',
                'manual_review_at',
                'fulfillment_retry_count',
                'next_fulfillment_retry_at',
            ]);
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['provider', 'status', 'created_at']);
            $table->dropColumn(['failure_reason', 'processed_at']);
        });
    }
};
