
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('business_name');

            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();

            $table->string('trade_license_number')->nullable();
            $table->string('tax_number')->nullable();

            $table->string('status')->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
