<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('pickup_address_id')
                ->constrained('addresses')
                ->restrictOnDelete();

            $table->foreignId('dropoff_address_id')
                ->constrained('addresses')
                ->restrictOnDelete();

            $table->string('tracking_number')->unique();

            $table->string('status')->default('draft');

            $table->string('recipient_name');
            $table->string('recipient_phone');

            $table->string('package_type')->nullable();

            $table->decimal('weight', 8, 2)->nullable();

            $table->decimal('delivery_fee', 10, 2)->nullable();

            $table->char('currency', 3)->default('AED');

            $table->text('special_instructions')->nullable();

            $table->timestamp('scheduled_at')->nullable();

            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
