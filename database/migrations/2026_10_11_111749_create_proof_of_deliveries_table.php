<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->unique()
                ->constrained('deliveries')
                ->cascadeOnDelete();

            $table->foreignId('driver_id')
                ->constrained('drivers');

            $table->string('recipient_name');
            $table->text('notes')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();

            $table->timestamp('confirmed_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proof_of_deliveries');
    }
};
