<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_deliveries', function (Blueprint $table) {
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

            $table->string('recipient_name');
            $table->string('recipient_phone', 30);

            $table->string('package_type')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('special_instructions')->nullable();

            $table->string('frequency');
            $table->string('timezone')->default('Asia/Dubai');

            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->time('execution_time');

            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index(['merchant_id', 'is_active']);
        });

        Schema::create('recurring_delivery_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recurring_delivery_id')
                ->constrained('recurring_deliveries')
                ->restrictOnDelete();

            $table->foreignId('delivery_id')
                ->nullable()
                ->constrained('deliveries')
                ->nullOnDelete();

            $table->timestamp('scheduled_for');

            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'recurring_delivery_id',
                'scheduled_for',
            ], 'recurring_run_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_delivery_runs');
        Schema::dropIfExists('recurring_deliveries');
    }
};
