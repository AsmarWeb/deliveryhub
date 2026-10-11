
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['delivery_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_histories');
    }
};
