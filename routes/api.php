<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\WalletApiController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes for Next.js Frontend
|--------------------------------------------------------------------------
| Ces endpoints fournissent les données et gèrent la logique métier pour
| le frontend Next.js (Boutique, Commande, Espace client, Solde, Webhooks).
*/

// Catalogue public
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{slug}', [CatalogController::class, 'show']);

// Commandes
Route::post('/orders', [OrderApiController::class, 'store']);
Route::get('/orders/{id}', [OrderApiController::class, 'show']);
Route::get('/orders/{id}/resume', [OrderApiController::class, 'resume']);

// Portefeuille / Solde
Route::post('/wallet/deposit', [WalletApiController::class, 'deposit']);
Route::get('/wallet/deposit/{id}/status', [WalletApiController::class, 'status']);

// Webhook pawaPay
Route::post('/webhooks/pawapay', [WebhookController::class, 'pawapay']);

// Tâche planifiée externe (identique à Next.js /api/cron/tick)
Route::match(['get', 'post'], '/cron/tick', [\App\Http\Controllers\Api\CronController::class, 'tick']);
