<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('merchant_lead_activities', function(Blueprint $table) {
   $table->id();
   $table->foreignId('merchant_lead_id')->constrained()->cascadeOnDelete();
   $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $table->string('type',30);
   $table->text('details');
   $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('merchant_lead_activities'); }
};
