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
        DB::transaction(function () {
            $supplierIds = DB::table('products')->pluck('supplier_id')->unique();

            foreach ($supplierIds as $sid) {
                $products = DB::table('products')->where('supplier_id', $sid)->get();
                if ($products->count() <= 1) {
                    continue;
                }

                // Si un des doublons est déjà référencé par une commande, on le garde en priorité
                $orderedProductIds = DB::table('orders')->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->unique();
                $canonicalId = $orderedProductIds->count() > 0 ? $orderedProductIds->first() : $products->first()->id;

                $duplicateIds = $products->pluck('id')->filter(fn ($id) => $id !== $canonicalId)->values()->all();

                if (!empty($duplicateIds)) {
                    // Réassigner les commandes et alertes vers le produit canonique
                    DB::table('orders')->whereIn('product_id', $duplicateIds)->update(['product_id' => $canonicalId]);
                    DB::table('stock_alerts')->whereIn('product_id', $duplicateIds)->delete();

                    // Supprimer les doublons
                    DB::table('products')->whereIn('id', $duplicateIds)->delete();
                }
            }
        });

        // Ajouter une contrainte d'unicité sur supplier_id
        $existingIndexes = collect(Schema::getIndexes('products'))->pluck('name')->toArray();

        Schema::table('products', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('products_supplier_id_index', $existingIndexes, true)) {
                $table->dropIndex('products_supplier_id_index');
            }
            if (!in_array('products_supplier_id_unique', $existingIndexes, true)) {
                $table->unique('supplier_id', 'products_supplier_id_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingIndexes = collect(Schema::getIndexes('products'))->pluck('name')->toArray();

        Schema::table('products', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('products_supplier_id_unique', $existingIndexes, true)) {
                $table->dropUnique('products_supplier_id_unique');
            }
            if (!in_array('products_supplier_id_index', $existingIndexes, true)) {
                $table->index('supplier_id', 'products_supplier_id_index');
            }
        });
    }
};
