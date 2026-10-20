<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('delivery_assignments', fn (Blueprint $table) => $table->boolean('is_automatic')->default(false));
        Schema::table('deliveries', fn (Blueprint $table) => $table->timestamp('dispatch_attempted_at')->nullable()->index());
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('dispatch_enabled')->default(false);
            $table->timestamp('dispatch_seen_at')->nullable()->index();
            $table->decimal('dispatch_latitude', 10, 7)->nullable();
            $table->decimal('dispatch_longitude', 10, 7)->nullable();
            $table->timestamp('dispatch_location_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('delivery_assignments', fn (Blueprint $table) => $table->dropColumn('is_automatic'));
        Schema::table('deliveries', function (Blueprint $table) { $table->dropIndex(['dispatch_attempted_at']); $table->dropColumn('dispatch_attempted_at'); });
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['dispatch_seen_at']);
            $table->dropColumn(['dispatch_enabled', 'dispatch_seen_at', 'dispatch_latitude', 'dispatch_longitude', 'dispatch_location_at']);
        });
    }
};
