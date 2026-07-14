<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('launch_voucher_id')->nullable()->after('payable_amount')->constrained('launch_vouchers')->nullOnDelete();
            $table->string('voucher_code')->nullable()->after('launch_voucher_id');
            $table->unsignedInteger('voucher_discount_amount')->default(0)->after('voucher_code');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('launch_voucher_id');
            $table->dropColumn(['voucher_code', 'voucher_discount_amount']);
        });
    }
};
