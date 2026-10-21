<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->string('platform', 10);
            $table->string('language', 2)->default('en');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('push_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('push_devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->index();
            $table->string('ticket_id')->nullable();
            $table->timestamp('ticket_at')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'notification_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('push_outbox'); Schema::dropIfExists('push_devices'); }
};
