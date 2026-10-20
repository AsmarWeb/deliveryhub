<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_cod_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('expected_amount', 12, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('status', 20)->default('expected');
            $table->foreignId('collected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_cod_records');
    }
};
