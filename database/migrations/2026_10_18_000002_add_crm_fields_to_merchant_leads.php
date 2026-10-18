<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('merchant_leads', function(Blueprint $table) {
   $table->timestamp('next_follow_up_at')->nullable();
   $table->unsignedTinyInteger('priority')->default(3);
   $table->text('ai_summary')->nullable();
   $table->text('ai_recommendation')->nullable();
   $table->timestamp('ai_analyzed_at')->nullable();
  });
 }
 public function down(): void {
  Schema::table('merchant_leads', fn(Blueprint $table) => $table->dropColumn(['next_follow_up_at','priority','ai_summary','ai_recommendation','ai_analyzed_at']));
 }
};
