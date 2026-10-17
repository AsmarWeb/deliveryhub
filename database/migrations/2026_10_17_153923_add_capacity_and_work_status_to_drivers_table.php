<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_active_deliveries')
                ->default(5);

            $table->string('work_status', 20)
                ->default('offline');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'max_active_deliveries',
                'work_status',
            ]);
        });
    }
};
