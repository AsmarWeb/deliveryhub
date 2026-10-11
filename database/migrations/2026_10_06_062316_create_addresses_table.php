
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->nullable()
                ->constrained('merchants')
                ->restrictOnDelete();

            $table->string('label')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();

            $table->string('country')->default('United Arab Emirates');
            $table->string('city');

            $table->string('area')->nullable();
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('unit')->nullable();

            $table->text('address_line');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->text('delivery_instructions')->nullable();

            $table->timestamps();

            $table->index(['merchant_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
