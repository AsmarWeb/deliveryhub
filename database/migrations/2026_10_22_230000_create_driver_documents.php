<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->date('expires_on')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'type', 'superseded_at'], 'driver_docs_current');
        });
        Schema::create('driver_document_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('driver_document_id');
            $table->foreign('driver_document_id')->references('id')->on('driver_documents')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_document_reviews');
        Schema::dropIfExists('driver_documents');
    }
};
