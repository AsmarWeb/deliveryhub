<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('driver_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status')->default('pending');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['delivery_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
