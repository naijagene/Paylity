<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('category_key', 32);
            $table->string('service_id', 64);
            $table->string('service_name', 64);
            $table->string('display_name');
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'category_key', 'service_name']);
            $table->index(['provider', 'category_key', 'is_active']);
        });

        Schema::create('provider_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_service_id')->constrained()->cascadeOnDelete();
            $table->string('variation_code', 128);
            $table->string('name');
            $table->unsignedInteger('amount')->nullable();
            $table->boolean('fixed_price')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider_service_id', 'variation_code']);
            $table->index(['provider_service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_variations');
        Schema::dropIfExists('provider_services');
        Schema::dropIfExists('product_categories');
    }
};
