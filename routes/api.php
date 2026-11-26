<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\CronController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Catalogue public en lecture seule
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{slug}', [CatalogController::class, 'show']);

// Webhook CinetPay (Cartes Bancaires + Mobile Money)
Route::match(['get', 'post'], '/webhooks/cinetpay', [WebhookController::class, 'cinetpay'])->middleware('throttle:120,1');

// Webhook NOWPayments (crypto-monnaies)
Route::post('/webhooks/nowpayments', [WebhookController::class, 'nowpayments'])->middleware('throttle:120,1')->name('webhooks.nowpayments');

// Tâche planifiée externe sécurisée par Bearer token CRON_SECRET
Route::match(['get', 'post'], '/cron/tick', [CronController::class, 'tick']);
