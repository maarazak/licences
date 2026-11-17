<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\JournalController;

/*
|--------------------------------------------------------------------------
| Web Routes (Backoffice Admin Blade)
|--------------------------------------------------------------------------
*/

// Redirection vers le backoffice admin
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('admin')->name('admin.')->group(function () {
    // Connexion / Déconnexion
    Route::get('connexion', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('connexion', [AuthController::class, 'login']);
    Route::post('deconnexion', [AuthController::class, 'logout'])->name('logout');

    // Backoffice protégé par authentification et rôle admin (Spatie)
    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Commandes
        Route::get('commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('commandes/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('commandes/{id}/note', [OrderController::class, 'saveNote'])->name('orders.note');
        Route::post('commandes/{id}/refund-balance', [OrderController::class, 'refundBalance'])->name('orders.refund_balance');
        Route::post('commandes/{id}/refund-gateway', [OrderController::class, 'refundGateway'])->name('orders.refund_gateway');

        // Produits
        Route::get('produits', [ProductController::class, 'index'])->name('products.index');
        Route::get('produits/{id}', [ProductController::class, 'edit'])->name('products.edit');
        Route::post('produits/{id}', [ProductController::class, 'update'])->name('products.update');
        Route::post('produits/{id}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle_active');

        // Clients
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');
        Route::post('clients/{id}/adjust-balance', [ClientController::class, 'adjustBalance'])->name('clients.adjust_balance');
        Route::post('clients/{id}/toggle-block', [ClientController::class, 'toggleBlock'])->name('clients.toggle_block');

        // Assistance
        Route::get('assistance', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('assistance/{id}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('assistance/{id}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('assistance/{id}/close', [TicketController::class, 'close'])->name('tickets.close');

        // Journal d'audit
        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');

        // Paramètres
        Route::get('parametres', [SettingController::class, 'index'])->name('settings.index');
        Route::post('parametres', [SettingController::class, 'update'])->name('settings.update');
    });
});
