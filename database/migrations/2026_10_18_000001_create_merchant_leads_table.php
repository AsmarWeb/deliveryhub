<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('merchant_leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('emirate', 30);
            $table->string('business_type', 80);
            $table->string('monthly_deliveries', 30)->nullable();
            $table->text('message')->nullable();
            $table->string('preferred_language', 2)->default('ar');
            $table->string('status', 20)->default('new')->index();
            $table->string('source', 30)->default('landing');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('merchant_leads'); }
};
