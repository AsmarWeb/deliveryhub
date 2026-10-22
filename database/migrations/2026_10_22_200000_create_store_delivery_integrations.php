<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_connections', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(false);
            $table->foreignId('delivery_pickup_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('delivery_key_hash', 64)->nullable()->unique();
            $table->string('delivery_key_prefix', 24)->nullable();
            $table->text('delivery_webhook_url')->nullable();
            $table->text('delivery_webhook_secret')->nullable();
        });
        Schema::create('integration_delivery_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('catalog_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->string('external_order_id', 120);
            $table->string('external_order_hash', 64);
            $table->string('idempotency_hash', 64);
            $table->string('payload_hash', 64);
            $table->longText('payload');
            $table->text('tracking_token');
            $table->string('tracking_hash', 64)->unique();
            $table->timestamps();
            $table->unique(['catalog_connection_id', 'external_order_hash'], 'integration_order_reference_unique');
            $table->unique(['catalog_connection_id', 'idempotency_hash'], 'integration_order_idempotency_unique');
        });
        Schema::create('integration_delivery_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('integration_delivery_order_id');
            $table->foreign('integration_delivery_order_id', 'delivery_webhook_order_fk')->references('id')->on('integration_delivery_orders')->cascadeOnDelete();
            $table->foreignId('delivery_status_history_id')->unique()->constrained('delivery_status_histories')->restrictOnDelete();
            $table->longText('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_delivery_webhooks');
        Schema::dropIfExists('integration_delivery_orders');
        Schema::table('catalog_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_pickup_address_id');
            $table->dropUnique(['delivery_key_hash']);
            $table->dropColumn(['delivery_enabled', 'delivery_key_hash', 'delivery_key_prefix', 'delivery_webhook_url', 'delivery_webhook_secret']);
        });
    }
};
