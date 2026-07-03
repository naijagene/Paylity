<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receipt_verification_token', 64)->nullable()->unique()->after('verified_phone');
            $table->timestamp('fulfilled_at')->nullable()->after('receipt_verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['receipt_verification_token', 'fulfilled_at']);
        });
    }
};
