<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_voucher_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->string('network')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('one_per_phone')->default(true);
            $table->boolean('one_per_email')->default(false);
            $table->boolean('one_per_device')->default(true);
            $table->boolean('shared_code')->default(false);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('launch_vouchers', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('id')
                ->constrained('launch_voucher_campaigns')
                ->nullOnDelete();
            $table->string('code_normalized')->nullable()->after('code');
            $table->unique('code_normalized');
        });

        Schema::table('launch_voucher_redemptions', function (Blueprint $table) {
            $table->timestamp('reserved_at')->nullable()->after('status');
            $table->timestamp('released_at')->nullable()->after('reserved_at');
            $table->unique('transaction_id');
        });

        DB::table('launch_vouchers')->orderBy('id')->get()->each(function (object $voucher): void {
            DB::table('launch_vouchers')
                ->where('id', $voucher->id)
                ->update([
                    'code_normalized' => strtoupper(preg_replace('/[\s\-]+/', '', (string) $voucher->code)),
                ]);
        });

        DB::table('launch_voucher_redemptions')
            ->where('status', 'pending')
            ->update(['status' => 'reserved', 'reserved_at' => now()]);

        DB::table('launch_voucher_redemptions')
            ->where('status', 'completed')
            ->update(['status' => 'redeemed']);

        DB::table('launch_voucher_redemptions')
            ->where('status', 'cancelled')
            ->update(['status' => 'released', 'released_at' => now()]);

        $legacyCodes = ['PAYLITY500', 'PAYLITY1000', 'SOFT500', 'SOFT1000', 'WELCOME500'];

        DB::table('launch_vouchers')
            ->whereIn('code', $legacyCodes)
            ->update(['active' => false]);
    }

    public function down(): void
    {
        Schema::table('launch_voucher_redemptions', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
            $table->dropColumn(['reserved_at', 'released_at']);
        });

        Schema::table('launch_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropUnique(['code_normalized']);
            $table->dropColumn('code_normalized');
        });

        Schema::dropIfExists('launch_voucher_campaigns');
    }
};
