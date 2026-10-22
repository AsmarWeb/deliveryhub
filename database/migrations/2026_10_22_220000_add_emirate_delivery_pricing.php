<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_pricing_settings', function (Blueprint $table) {
            $table->boolean('emirate_pricing_enabled')->default(false);
            $table->json('emirate_rates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_pricing_settings', function (Blueprint $table) {
            $table->dropColumn(['emirate_pricing_enabled', 'emirate_rates']);
        });
    }
};
