<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\JournalController;

// Shop & Client Controllers
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Account\AccountController;

/*
|--------------------------------------------------------------------------
| Public Boutique & Shop Routes (Vue 3 / Inertia)
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/boutique', [CatalogController::class, 'index'])->name('shop.index');
Route::get('/boutique/{slug}', [CatalogController::class, 'show'])->name('shop.show');

// Checkout & Payments
Route::get('/commande/validation', [CheckoutController::class, 'showValidation'])->name('checkout.validation');
Route::post('/commande/validation', [CheckoutController::class, 'createOrder'])->name('checkout.store');
Route::get('/paiement/retour', [CheckoutController::class, 'showReturn'])->name('payment.return');
Route::get('/paiement/reprendre', [CheckoutController::class, 'resumeOrder'])->name('payment.resume');

// Pages institutionnelles
Route::get('/comment-ca-marche', [PageController::class, 'howItWorks'])->name('pages.how_it_works');
Route::get('/assistance', [PageController::class, 'support'])->name('pages.support');
Route::get('/conditions', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/confidentialite', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/mentions-legales', [PageController::class, 'legal'])->name('pages.legal');

/*
|--------------------------------------------------------------------------
| Client Authentication (Code par e-mail)
|--------------------------------------------------------------------------
*/

Route::get('/connexion', [ClientAuthController::class, 'showLogin'])->name('login');
Route::post('/connexion/demander-code', [ClientAuthController::class, 'requestCode'])->name('login.request_code');
Route::post('/connexion/verifier-code', [ClientAuthController::class, 'verifyCode'])->name('login.verify_code');
Route::post('/deconnexion', [ClientAuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Espace Client (Protégé par auth:web)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web'])->prefix('compte')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/achats', [AccountController::class, 'orders'])->name('orders.index');
    Route::get('/acces', [AccountController::class, 'accessList'])->name('access.index');
    Route::get('/achats/{id}', [AccountController::class, 'orderDetail'])->name('orders.show');
    Route::post('/achats/{id}/avoir', [AccountController::class, 'chooseCredit'])->name('orders.credit');
    Route::post('/achats/{id}/remboursement', [AccountController::class, 'requestRefund'])->name('orders.refund');

    // Solde
    Route::get('/solde', [AccountController::class, 'wallet'])->name('wallet.index');
    Route::post('/solde/recharger', [AccountController::class, 'deposit'])->name('wallet.deposit');

    // Assistance & Tickets
    Route::get('/assistance', [AccountController::class, 'tickets'])->name('tickets.index');
    Route::get('/assistance/nouveau', [AccountController::class, 'newTicket'])->name('tickets.create');
    Route::post('/assistance', [AccountController::class, 'createTicket'])->name('tickets.store');
    Route::get('/assistance/{id}', [AccountController::class, 'showTicket'])->name('tickets.show');
    Route::post('/assistance/{id}/repondre', [AccountController::class, 'replyTicket'])->name('tickets.reply');

    // Alertes
    Route::get('/alertes', [AccountController::class, 'alerts'])->name('alerts.index');
    Route::post('/alertes', [AccountController::class, 'createAlert'])->name('alerts.store');
    Route::delete('/alertes/{id}', [AccountController::class, 'deleteAlert'])->name('alerts.destroy');

    // Profil
    Route::get('/profil', [AccountController::class, 'profile'])->name('profile.index');
    Route::post('/profil', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profil/deconnecter-partout', [AccountController::class, 'logoutEverywhere'])->name('profile.logout_everywhere');
});

/*
|--------------------------------------------------------------------------
| Backoffice Admin (Blade, Spatie Permission)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    // Connexion / Déconnexion Admin avec 2FA par e-mail
    Route::get('connexion', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('connexion', [AdminAuthController::class, 'login']);
    Route::post('connexion/verifier', [AdminAuthController::class, 'verify2fa'])->name('2fa.verify');
    Route::get('connexion/annuler', [AdminAuthController::class, 'cancel2fa'])->name('2fa.cancel');
    Route::post('deconnexion', [AdminAuthController::class, 'logout'])->name('logout');

    // Backoffice protégé furtivement (404 si non admin)
    Route::middleware([\App\Http\Middleware\EnsureAdminAuthenticated::class])->group(function () {
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
