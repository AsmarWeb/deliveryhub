
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('phone')->nullable();

            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_plate')->nullable();

            $table->string('license_number')->nullable();

            $table->string('status')->default('pending');

            $table->boolean('is_available')->default(false);

            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();

            $table->timestamp('last_location_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
