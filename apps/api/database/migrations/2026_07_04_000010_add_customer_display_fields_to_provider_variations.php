<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_variations', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->boolean('is_visible')->default(true)->after('is_active');
            $table->boolean('is_popular')->default(false)->after('is_visible');
            $table->unsignedInteger('sort_order')->nullable()->after('is_popular');
            $table->string('customer_category', 32)->nullable()->after('sort_order');
            $table->string('validity_label', 64)->nullable()->after('customer_category');
            $table->string('data_size_label', 64)->nullable()->after('validity_label');
            $table->boolean('display_override')->default(false)->after('data_size_label');

            $table->index(['provider_service_id', 'is_active', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_variations', function (Blueprint $table) {
            $table->dropIndex(['provider_service_id', 'is_active', 'is_visible']);
            $table->dropColumn([
                'display_name',
                'is_visible',
                'is_popular',
                'sort_order',
                'customer_category',
                'validity_label',
                'data_size_label',
                'display_override',
            ]);
        });
    }
};
