<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()
                ->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')
                ->constrained()->restrictOnDelete();
            $table->foreignId('merchant_subscription_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('platform_amount', 12, 2);
            $table->decimal('partner_amount', 12, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('status', 20)->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_commissions');
    }
};
