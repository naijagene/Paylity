<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('provider', 32)->default('vtpass');
            $table->string('request_id', 64)->nullable();
            $table->string('outcome', 32);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('actor', 32)->default('system');
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['transaction_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_attempts');
    }
};
