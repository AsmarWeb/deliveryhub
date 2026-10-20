<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('base_fee', 10, 2)->default(10);
            $table->decimal('price_per_km', 10, 2)->default(1.50);
            $table->decimal('minimum_fee', 10, 2)->default(10);
            $table->decimal('maximum_distance_km', 8, 2)->default(50);
            $table->boolean('auto_pricing_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_pricing_settings');
    }
};
