<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_attempts', function (Blueprint $table) {
            $table->string('trigger_source', 32)->nullable()->after('attempt_number');
            $table->string('status', 32)->nullable()->after('outcome');
            $table->string('provider_reference', 128)->nullable()->after('request_id');
            $table->string('provider_code', 32)->nullable()->after('provider_reference');
            $table->string('provider_message')->nullable()->after('provider_code');
            $table->timestamp('started_at')->nullable()->after('attempted_at');
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            $table->timestamp('resolved_at')->nullable()->after('submitted_at');
            $table->timestamp('next_retry_at')->nullable()->after('resolved_at');
            $table->string('error_class', 64)->nullable()->after('failure_reason');
            $table->string('error_code', 64)->nullable()->after('error_class');
            $table->string('error_message')->nullable()->after('error_code');
            $table->string('created_by_operator', 64)->nullable()->after('actor');
            $table->string('successful_attempt_key', 64)->nullable()->after('created_by_operator');

            $table->unique('successful_attempt_key');
            $table->unique('request_id');
            $table->index(['transaction_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_attempts', function (Blueprint $table) {
            $table->dropUnique(['successful_attempt_key']);
            $table->dropUnique(['request_id']);
            $table->dropIndex(['transaction_id', 'status']);
            $table->dropIndex(['status', 'submitted_at']);

            $table->dropColumn([
                'trigger_source',
                'status',
                'provider_reference',
                'provider_code',
                'provider_message',
                'started_at',
                'submitted_at',
                'resolved_at',
                'next_retry_at',
                'error_class',
                'error_code',
                'error_message',
                'created_by_operator',
                'successful_attempt_key',
            ]);
        });
    }
};
