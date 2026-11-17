<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'notify_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('notify_token', 191)->nullable()->after('payment_url');
            });
        }

        if (Schema::hasTable('wallet_deposits') && !Schema::hasColumn('wallet_deposits', 'notify_token')) {
            Schema::table('wallet_deposits', function (Blueprint $table) {
                $table->string('notify_token', 191)->nullable()->after('payment_url');
            });
        }

        // Migrate past records from pawapay to cinetpay
        if (Schema::hasTable('orders')) {
            DB::table('orders')->where('paid_with', 'pawapay')->update(['paid_with' => 'cinetpay']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'notify_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('notify_token');
            });
        }

        if (Schema::hasTable('wallet_deposits') && Schema::hasColumn('wallet_deposits', 'notify_token')) {
            Schema::table('wallet_deposits', function (Blueprint $table) {
                $table->dropColumn('notify_token');
            });
        }
    }
};
