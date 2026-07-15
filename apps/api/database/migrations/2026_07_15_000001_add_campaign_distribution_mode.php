<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('launch_voucher_campaigns', function (Blueprint $table) {
            $table->string('distribution_mode')->default('unique_codes')->after('network');
            $table->unsignedInteger('max_redemptions')->nullable()->after('generated_count');
            $table->unsignedInteger('reservation_timeout_minutes')->default(30)->after('one_per_device');
        });

        DB::table('launch_voucher_campaigns')
            ->where('shared_code', true)
            ->update(['distribution_mode' => 'shared_code']);

        Schema::table('launch_voucher_redemptions', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('launch_voucher_id')->constrained('launch_voucher_campaigns')->nullOnDelete();
            $table->string('customer_phone_normalized', 16)->nullable()->after('customer_phone');
            $table->string('customer_email_hash', 64)->nullable()->after('customer_email');
            $table->string('device_id_hash', 64)->nullable()->after('device_id');

            $table->index(['campaign_id', 'customer_phone_normalized'], 'lv_redemptions_campaign_phone_idx');
            $table->index(['campaign_id', 'customer_email_hash'], 'lv_redemptions_campaign_email_idx');
            $table->index(['campaign_id', 'device_id_hash'], 'lv_redemptions_campaign_device_idx');
        });

        DB::table('launch_voucher_redemptions')
            ->whereNull('campaign_id')
            ->get()
            ->each(function (object $redemption): void {
                $campaignId = DB::table('launch_vouchers')
                    ->where('id', $redemption->launch_voucher_id)
                    ->value('campaign_id');

                if ($campaignId) {
                    DB::table('launch_voucher_redemptions')
                        ->where('id', $redemption->id)
                        ->update(['campaign_id' => $campaignId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('launch_voucher_redemptions', function (Blueprint $table) {
            $table->dropIndex('lv_redemptions_campaign_phone_idx');
            $table->dropIndex('lv_redemptions_campaign_email_idx');
            $table->dropIndex('lv_redemptions_campaign_device_idx');
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn(['customer_phone_normalized', 'customer_email_hash', 'device_id_hash']);
        });

        Schema::table('launch_voucher_campaigns', function (Blueprint $table) {
            $table->dropColumn(['distribution_mode', 'max_redemptions', 'reservation_timeout_minutes']);
        });
    }
};
