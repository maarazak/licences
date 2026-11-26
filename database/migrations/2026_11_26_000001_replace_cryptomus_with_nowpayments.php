<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cryptomus est remplacé par NOWPayments : passerelle mémorisée sur les recharges, anciennes valeurs renommées. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('wallet_deposits', 'gateway')) {
            Schema::table('wallet_deposits', function (Blueprint $table) {
                $table->string('gateway', 30)->default('cinetpay')->after('reference');
            });
        }

        // Anciennes recharges crypto (Cryptomus) : marquées comme crypto.
        DB::table('wallet_deposits')->where('payment_url', 'like', '%cryptomus%')->update(['gateway' => 'nowpayments']);
        DB::table('orders')->where('paid_with', 'cryptomus')->update(['paid_with' => 'nowpayments']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('wallet_deposits', 'gateway')) {
            Schema::table('wallet_deposits', fn (Blueprint $table) => $table->dropColumn('gateway'));
        }
    }
};
