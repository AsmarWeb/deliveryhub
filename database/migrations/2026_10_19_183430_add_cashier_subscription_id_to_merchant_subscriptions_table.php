<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('merchant_subscriptions', 'cashier_subscription_id')) {
            Schema::table('merchant_subscriptions', function (Blueprint $table) {
                $table->foreignId('cashier_subscription_id')
                    ->nullable()
                    ->unique()
                    ->constrained('subscriptions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('merchant_subscriptions', 'cashier_subscription_id')) {
            Schema::table('merchant_subscriptions', function (Blueprint $table) {
                $table->dropUnique(
                    'merchant_subscriptions_cashier_subscription_id_unique'
                );

                $table->dropForeign(
                    ['cashier_subscription_id']
                );

                $table->dropColumn('cashier_subscription_id');
            });
        }
    }
};
