<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->string('event_type', 64);
            $table->string('reference', 32)->nullable();
            $table->string('payload_hash', 64);
            $table->string('status', 32)->default('processed');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['reference', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
