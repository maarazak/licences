<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produits gérés manuellement (créés depuis le backoffice, jamais touchés par la synchronisation du fournisseur) et leur
 * stock : une ligne par accès à livrer (compte, lien, code), chiffrée au repos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'source')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('source', 16)->default('supplier')->index()->after('supplier_id');
            });
        }

        if (!Schema::hasTable('product_stock_items')) {
            Schema::create('product_stock_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id')->index();
                $table->text('content_encrypted');
                $table->string('status', 12)->default('available')->index(); // available | sold
                $table->uuid('order_id')->nullable()->index();
                $table->timestamp('sold_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_items');
        if (Schema::hasColumn('products', 'source')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('source'));
        }
    }
};
