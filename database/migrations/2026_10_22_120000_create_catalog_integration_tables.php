<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('mode', 20)->default('push');
            $table->string('status', 20)->default('active');
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 24);
            $table->json('scopes');
            $table->text('feed_url')->nullable();
            $table->text('feed_token')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable()->index();
            $table->string('last_sync_error', 200)->nullable();
            $table->timestamps();
        });
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 120);
            $table->char('external_key', 64);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('sku', 120)->nullable();
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('stock_quantity');
            $table->string('status', 20)->default('active');
            $table->text('image_url')->nullable();
            $table->text('product_url')->nullable();
            $table->timestamp('source_updated_at');
            $table->timestamps();
            $table->unique(['catalog_connection_id', 'external_key']);
            $table->index(['status', 'stock_quantity']);
        });
        Schema::create('catalog_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('catalog_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('changed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->string('error_code', 100)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_sync_runs');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_connections');
    }
};
