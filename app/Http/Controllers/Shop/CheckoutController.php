<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\MailService;
use App\Services\OrderFulfillmentService;
use App\Services\CinetPayService;
use App\Services\CryptomusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function showValidation(Request $request)
    {
        $slug = $request->query('produit');
        if (!$slug) {
            return redirect()->route('shop.index');
        }

        // Connexion requise identique à requireUser(next)
        if (!Auth::guard('web')->check()) {
            return redirect()->route('login', [
                'next' => '/commande/validation?produit=' . urlencode($slug),
            ]);
        }

        $user = Auth::guard('web')->user();

        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->where('supplier_active', true)
            ->firstOrFail();

        $isAvailable = $product->supplier_purchasable
            && $product->in_stock
            && !$product->preorder
            && ($product->stock === null || $product->stock > 0);

        $duration = $product->duration;

        $accessKind = preg_match('/account|link|code/i', (string) $product->category)
            ? $product->category
            : ($product->delivery_kind ?: 'key');

        $accessLabel = match (strtolower($accessKind)) {
            'account' => 'Compte fourni',
            'link' => 'Lien d\'invitation',
            'code' => 'Code d\'activation',
            default => 'Clé de licence',
        };

        return Inertia::render('Checkout/Validation', [
            'clientToken' => Str::uuid()->toString(),
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'title' => $product->display_name ?: $product->name,
                'unitPriceXof' => (int) $product->price_xof,
                'available' => $isAvailable,
                'stock' => $product->stock,
                'maxQuantity' => min(10, $product->stock ?? 10),
                'requiresEmail' => (bool) $product->requires_email,
                'accessLabel' => $accessLabel,
                'durationLabel' => $duration,
                'warranty' => $product->warranty,
            ],
            'defaults' => [
                'fullName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? ''),
                'firstName' => $user->first_name ?? '',
                'lastName' => $user->last_name ?? '',
                'phone' => '',
                'country' => $user->country ?? 'BF',
                'email' => $user->email,
            ],
            'balanceXof' => (int) $user->balance_xof,
        ]);
    }

    public function createOrder(Request $request)
    {
        if (!Auth::guard('web')->check()) {
            return redirect()->route('login');
        }

        $user = Auth::guard('web')->user();
        if ($user->is_blocked) {
            return back()->with('error', 'Votre compte est suspendu. Veuillez contacter le support.');
        }

        // Supporte à la fois camelCase (Next.js) et snake_case
        $productId = $request->input('productId') ?: $request->input('product_id');
        $payWith = $request->input('payWith') ?: $request->input('paid_with');
        $quantity = (int) ($request->input('quantity', 1));
        $clientToken = $request->input('clientToken') ?: $request->input('client_token');

        $product = Product::where('id', $productId)
            ->where('is_published', true)
            ->where('supplier_active', true)
            ->firstOrFail();

        $unitPriceXof = (int) $product->price_xof;
        $totalXof = $unitPriceXof * $quantity;
        $expectedTotalXof = (int) ($request->input('expectedTotalXof') ?? $request->input('expected_total_xof') ?? $totalXof);

        // Validation
        $request->merge([
            'productId' => $productId,
            'payWith' => $payWith,
            'expectedTotalXof' => $expectedTotalXof,
        ]);

        $validated = $request->validate([
            'productId' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10',
            'fullName' => 'nullable|string|max:150',
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:32',
            'country' => 'nullable|string|max:5',
            'expectedTotalXof' => 'required|integer',
            'clientToken' => 'nullable|string|max:64',
            'activationEmails' => 'nullable|array',
            'activationEmails.*' => 'nullable|email|max:191',
            'payWith' => 'required|in:gateway,balance,pawapay,cinetpay,cryptomus,crypto',
        ]);

        // Normalisation payWith
        $payWith = match ($validated['payWith']) {
            'cryptomus', 'crypto' => 'cryptomus',
            'balance' => 'balance',
            default => 'cinetpay',
        };

        // Vérification anti-altération de prix
        if ($totalXof !== (int) $validated['expectedTotalXof']) {
            return back()->with('error', 'Le prix de cette offre a été mis à jour. Veuillez vérifier le montant et réessayer.');
        }

        // Idempotence : si une commande avec ce clientToken existe déjà pour ce client
        if (!empty($validated['clientToken'])) {
            $existing = Order::where('user_id', $user->id)
                ->where('client_token', $validated['clientToken'])
                ->first();

            if ($existing) {
                if ($existing->status === 'pending_payment' && $existing->payment_url) {
                    return Inertia::location($existing->payment_url);
                }
                return redirect()->route('account.orders.show', $existing->id);
            }
        }

        // Extraction prénom / nom
        $firstName = trim($validated['firstName'] ?? '');
        $lastName = trim($validated['lastName'] ?? '');
        if (!empty($validated['fullName']) && (empty($firstName) || empty($lastName))) {
            $parts = explode(' ', trim($validated['fullName']), 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';
        }
        if (empty($firstName)) $firstName = $user->first_name ?: ($user->name ?: 'Client');
        if (empty($lastName)) $lastName = $user->last_name ?: '';

        $phone = $validated['phone'] ?? $user->phone;
        $country = $validated['country'] ?? ($user->country ?: 'BF');

        // Mémorisation profil pour les futurs achats
        $user->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'country' => $country,
        ]);

        $orderId = Str::uuid()->toString();
        $ref = 'CMD-' . strtoupper(Str::random(8));
        $now = now();
        $emails = !empty($validated['activationEmails']) ? array_values(array_filter($validated['activationEmails'])) : null;

        // 1. Paiement par Solde Client
        if ($payWith === 'balance') {
            if ($user->balance_xof < $totalXof) {
                return back()->with('error', 'Votre solde est insuffisant pour régler cette commande entière.');
            }

            DB::transaction(function () use ($user, $totalXof, $orderId, $product, $quantity, $unitPriceXof, $ref, $firstName, $lastName, $phone, $country, $emails, $validated, $now) {
                $userLocked = User::lockForUpdate()->find($user->id);
                if ($userLocked->balance_xof < $totalXof) {
                    throw new \Exception('Solde insuffisant.');
                }

                $newBalance = $userLocked->balance_xof - $totalXof;
                $userLocked->balance_xof = $newBalance;
                $userLocked->save();

                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userLocked->id,
                    'amount_xof' => -$totalXof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'debit',
                    'kind' => 'purchase',
                    'description' => "Achat {$product->name} — {$ref}",
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'actor' => $userLocked->email,
                    'created_at' => $now,
                ]);

                Order::create([
                    'id' => $orderId,
                    'reference' => $ref,
                    'client_token' => $validated['clientToken'] ?? null,
                    'user_id' => $userLocked->id,
                    'product_id' => $product->id,
                    'product_name' => $product->display_name ?: $product->name,
                    'supplier_product_id' => $product->supplier_id,
                    'quantity' => $quantity,
                    'unit_price_xof' => $unitPriceXof,
                    'total_xof' => $totalXof,
                    'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
                    'usd_rate' => (int) \App\Models\Setting::get('usdRate', \App\Models\Setting::get('usd_rate', 669)),
                    'paid_with' => 'balance',
                    'status' => 'paid',
                    'paid_at' => $now,
                    'customer_first_name' => $firstName,
                    'customer_last_name' => $lastName,
                    'customer_email' => $userLocked->email,
                    'customer_phone' => $phone,
                    'customer_country' => $country,
                    'activation_emails' => $emails,
                    'created_at' => $now,
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $orderId,
                    'type' => 'paid',
                    'event' => 'paid',
                    'actor' => 'client',
                    'message' => "Commande réglée avec succès par solde du compte ({$totalXof} FCFA).",
                    'created_at' => $now,
                ]);
            });

            try {
                $savedOrder = Order::find($orderId);
                app(MailService::class)->sendOrderPaid($savedOrder);
                app(OrderFulfillmentService::class)->fulfil($orderId);
            } catch (\Throwable $e) {
                \Log::warning("Erreur fulfillment solde: " . $e->getMessage());
            }

            if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'seamless' => false,
                    'order_id' => $orderId,
                    'reference' => $ref,
                    'redirect_url' => route('account.orders.show', $orderId),
                ]);
            }

            return redirect()->route('account.orders.show', $orderId)->with('success', 'Commande validée et payée avec succès !');
        }

        // 2. Paiement via passerelle Cryptomus (Crypto-monnaies : USDT, BTC, ETH, TON, SOL)
        if ($payWith === 'cryptomus') {
            $order = Order::create([
                'id' => $orderId,
                'reference' => $ref,
                'client_token' => $validated['clientToken'] ?? null,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_name' => $product->display_name ?: $product->name,
                'supplier_product_id' => $product->supplier_id,
                'quantity' => $quantity,
                'unit_price_xof' => $unitPriceXof,
                'total_xof' => $totalXof,
                'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
                'usd_rate' => (int) \App\Models\Setting::get('usdRate', \App\Models\Setting::get('usd_rate', 669)),
                'paid_with' => 'cryptomus',
                'status' => 'pending_payment',
                'payment_checkout_id' => null,
                'payment_intent_id' => $ref,
                'customer_first_name' => $firstName,
                'customer_last_name' => $lastName,
                'customer_email' => $user->email,
                'customer_phone' => $phone,
                'customer_country' => $country,
                'activation_emails' => $emails,
                'created_at' => $now,
            ]);

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $orderId,
                'type' => 'created',
                'event' => 'created',
                'actor' => 'client',
                'message' => "Commande créée en attente de règlement en crypto-monnaies ({$totalXof} FCFA).",
                'created_at' => $now,
            ]);

            try {
                $cryptomus = app(CryptomusService::class);
                $returnUrl = url("/paiement/retour?commande={$order->id}");

                $session = $cryptomus->createPayment([
                    'order_id' => $ref,
                    'amount_xof' => $totalXof,
                    'return_url' => $returnUrl,
                ]);

                $paymentUrl = $session['payment_url'] ?? null;
                if (empty($paymentUrl)) {
                    throw new \Exception("URL de redirection introuvable.");
                }

                $order->update([
                    'payment_url' => $paymentUrl,
                    'payment_checkout_id' => $session['uuid'] ?? null,
                    'payment_intent_id' => $ref,
                ]);

                if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                    return response()->json([
                        'success' => true,
                        'order_id' => $orderId,
                        'reference' => $ref,
                        'payment_url' => $paymentUrl,
                        'return_url' => $returnUrl,
                    ]);
                }

                return Inertia::location($paymentUrl);
            } catch (\Throwable $e) {
                \Log::error("Erreur Cryptomus checkout : " . $e->getMessage());
                $order->update([
                    'status' => 'payment_failed',
                    'failure_message' => 'Service de paiement temporairement indisponible',
                ]);

                $genericError = "Ce moyen de paiement est temporairement indisponible. Si le problème persiste, veuillez contacter le service client.";

                if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                    return response()->json([
                        'success' => false,
                        'error' => $genericError,
                    ], 500);
                }

                return back()->with('error', $genericError);
            }
        }

        // 3. Paiement via passerelle CinetPay (Cartes Bancaires + Mobile Money)
        $order = Order::create([
            'id' => $orderId,
            'reference' => $ref,
            'client_token' => $validated['clientToken'] ?? null,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->display_name ?: $product->name,
            'supplier_product_id' => $product->supplier_id,
            'quantity' => $quantity,
            'unit_price_xof' => $unitPriceXof,
            'total_xof' => $totalXof,
            'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
            'usd_rate' => (int) \App\Models\Setting::get('usdRate', \App\Models\Setting::get('usd_rate', 669)),
            'paid_with' => 'cinetpay',
            'status' => 'pending_payment',
            'payment_checkout_id' => null,
            'payment_intent_id' => $ref,
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_email' => $user->email,
            'customer_phone' => $phone,
            'customer_country' => $country,
            'activation_emails' => $emails,
            'created_at' => $now,
        ]);

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $orderId,
            'type' => 'created',
            'event' => 'created',
            'actor' => 'client',
            'message' => "Commande créée en attente de règlement ({$totalXof} FCFA).",
            'created_at' => $now,
        ]);

        try {
            $cinetPay = app(CinetPayService::class);
            $returnUrl = url("/paiement/retour?commande={$order->id}");

            $session = $cinetPay->createPaymentPage([
                'order_id' => $orderId,
                'reference' => $ref,
                'amount_xof' => $totalXof,
                'country' => $country,
                'email' => $user->email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'return_url' => $returnUrl,
                'description' => "{$order->product_name} × {$quantity} — commande {$ref}",
            ]);

            $paymentUrl = $session['redirect_url'] ?? $session['redirectUrl'] ?? $session['payment_url'] ?? null;
            if (empty($paymentUrl)) {
                throw new \Exception("URL de redirection introuvable.");
            }

            $paymentToken = $session['payment_token'] ?? null;

            $order->update([
                'payment_url' => $paymentUrl,
                'payment_checkout_id' => $session['transaction_id'] ?? null,
                'payment_intent_id' => $session['merchant_transaction_id'] ?? $ref,
                'notify_token' => $session['notify_token'] ?? null,
            ]);

            if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'seamless' => true,
                    'order_id' => $orderId,
                    'reference' => $ref,
                    'payment_token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                    'return_url' => $returnUrl,
                ]);
            }

            return Inertia::location($paymentUrl);
        } catch (\Throwable $e) {
            \Log::error("Erreur CinetPay checkout : " . $e->getMessage());
            $order->update([
                'status' => 'payment_failed',
                'failure_message' => 'Service de paiement temporairement indisponible',
            ]);

            $genericError = "Ce moyen de paiement est temporairement indisponible. Si le problème persiste, veuillez contacter le service client.";

            if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'error' => $genericError,
                ], 422);
            }

            return back()->with('error', $genericError);
        }
    }

    public function showReturn(Request $request)
    {
        $orderId = $request->query('commande') ?: $request->query('order_id');
        $depositId = $request->query('depositId');
        $depotId = $request->query('depot');

        // Cas recharge de solde
        if ($depotId || (!$orderId && $depositId)) {
            $targetDepot = $depotId ?: $depositId;
            $walletDeposit = WalletDeposit::where('id', $targetDepot)
                ->orWhere('payment_checkout_id', $targetDepot)
                ->orWhere('payment_intent_id', $targetDepot)
                ->orWhere('reference', $targetDepot)
                ->first();

            if ($walletDeposit) {
                // Relecture chez CinetPay avec l'identifiant stocké ; le crédit n'a lieu qu'une fois (service dédié).
                $walletDeposit = app(\App\Services\WalletDepositService::class)->sync($walletDeposit);

                $refreshed = $walletDeposit->fresh();
                if ($refreshed && $refreshed->status === 'completed') {
                    return redirect()->route('account.wallet.index')->with('success', "Votre recharge de " . number_format($refreshed->amount_xof, 0, ',', ' ') . " FCFA a été validée avec succès !");
                }

                if ($refreshed && $refreshed->status === 'failed') {
                    return redirect()->route('account.wallet.index')->with('error', 'Le paiement de la recharge a échoué ou a été annulé auprès de la passerelle.');
                }

                return redirect()->route('account.wallet.index')->with('info', 'Paiement de la recharge en cours de confirmation.');
            }
        }

        // Cas commande de licence
        $targetOrder = $orderId ?: $depositId;
        if (!$targetOrder) {
            return redirect()->route('shop.index');
        }

        $order = Order::where('id', $targetOrder)
            ->orWhere('payment_checkout_id', $targetOrder)
            ->orWhere('payment_intent_id', $targetOrder)
            ->orWhere('reference', $targetOrder)
            ->firstOrFail();

        // Relecture sécurisée auprès de Cryptomus si commande payée en crypto
        if ($order->paid_with === 'cryptomus' && in_array($order->status, OrderFulfillmentService::PAYABLE_STATUSES, true)) {
            try {
                $info = app(CryptomusService::class)->getPaymentInfo($order->reference, $order->payment_checkout_id);
                app(OrderFulfillmentService::class)->settleCryptoPayment($order, is_array($info) ? $info : [], 'crypto');
            } catch (\Throwable $e) {
                \Log::warning("Erreur vérification Cryptomus lors du retour: " . $e->getMessage());
            }
        }

        // Relecture sécurisée auprès de CinetPay (identifiant stocké) : paiement en attente, ou tardif après expiration.
        if ($order->paid_with === 'cinetpay' && in_array($order->status, OrderFulfillmentService::PAYABLE_STATUSES, true)) {
            app(OrderFulfillmentService::class)->syncPayment($order->id);
        }

        $refreshedOrder = $order->fresh();

        // Si le paiement a réussi et a été enregistré, redirection directe vers le détail de la commande
        if ($refreshedOrder && $refreshedOrder->paid_at) {
            if ($refreshedOrder->status === 'delivered') {
                return redirect()->route('account.orders.show', $refreshedOrder->id)
                    ->with('success', 'Votre commande a été payée et votre licence est disponible !');
            }

            if ($refreshedOrder->status === 'failed') {
                return redirect()->route('account.orders.show', $refreshedOrder->id)
                    ->with('info', 'Paiement reçu avec succès. La commande est prise en charge par nos équipes pour régularisation.');
            }

            if (in_array($refreshedOrder->status, ['paid', 'processing', 'activation_awaiting'], true)) {
                return redirect()->route('account.orders.show', $refreshedOrder->id)
                    ->with('success', 'Paiement reçu avec succès ! Préparation de votre licence en cours.');
            }
        }

        return Inertia::render('Checkout/Return', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'total_xof' => (int) $order->total_xof,
                'status' => $refreshedOrder ? $refreshedOrder->status : $order->status,
                'paid_at' => $refreshedOrder && $refreshedOrder->paid_at ? $refreshedOrder->paid_at->toIso8601String() : null,
                'payment_url' => $order->payment_url,
            ],
        ]);
    }

    /**
     * Reprendre un paiement en attente ou réinitialiser la session de paiement CinetPay.
     */
    public function resumeOrder(Request $request)
    {
        $orderIdentifier = $request->query('commande')
            ?: $request->query('order_id')
            ?: $request->query('reference')
            ?: $request->query('ref');

        if (!$orderIdentifier) {
            return redirect()->route('account.orders.index')->with('error', 'Commande non spécifiée.');
        }

        if (!Auth::guard('web')->check()) {
            return redirect()->route('login', [
                'next' => $request->fullUrl(),
            ]);
        }

        $user = Auth::guard('web')->user();

        $order = Order::where(function ($q) use ($orderIdentifier) {
                $q->where('id', $orderIdentifier)
                  ->orWhere('reference', $orderIdentifier);
            })
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return redirect()->route('account.orders.index')
                ->with('error', 'Commande introuvable ou vous n\'avez pas l\'autorisation d\'y accéder.');
        }

        // Si la commande est déjà réglée
        if (in_array($order->status, ['paid', 'processing', 'completed', 'delivered', 'activation_awaiting'], true)) {
            return redirect()->route('account.orders.show', $order->id)
                ->with('info', 'Cette commande est déjà réglée.');
        }

        // Si la commande est annulée ou remboursée
        if (in_array($order->status, ['cancelled', 'expired', 'refunded'], true)) {
            return redirect()->route('account.orders.show', $order->id)
                ->with('error', 'Cette commande a été annulée ou n\'est plus payable.');
        }

        // Régénérer ou rafraîchir la session de paiement Cryptomus si commande payée en crypto
        if ($order->paid_with === 'cryptomus') {
            try {
                $cryptomus = app(CryptomusService::class);
                $returnUrl = url("/paiement/retour?commande={$order->id}");

                $session = $cryptomus->createPayment([
                    'order_id' => $order->reference,
                    'amount_xof' => (int) $order->total_xof,
                    'return_url' => $returnUrl,
                ]);

                $paymentUrl = $session['payment_url'] ?? null;
                if (!empty($paymentUrl)) {
                    $order->update([
                        'status' => 'pending_payment',
                        'payment_url' => $paymentUrl,
                        'payment_checkout_id' => $session['uuid'] ?? $order->payment_checkout_id,
                    ]);

                    return Inertia::location($paymentUrl);
                }
            } catch (\Throwable $e) {
                \Log::warning("Reprise de paiement: échec de régénération session Cryptomus ({$e->getMessage()}), tentative avec lien existant.");
            }

            if ($order->payment_url) {
                return Inertia::location($order->payment_url);
            }
        }

        // Régénérer ou rafraîchir la session de paiement CinetPay
        try {
            $cinetPay = app(CinetPayService::class);
            $returnUrl = url("/paiement/retour?commande={$order->id}");

            $session = $cinetPay->createPaymentPage([
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_xof' => (int) $order->total_xof,
                'country' => $order->customer_country ?: ($user->country ?: 'BF'),
                'email' => $order->customer_email ?: $user->email,
                'firstName' => $order->customer_first_name ?: ($user->first_name ?: 'Client'),
                'lastName' => $order->customer_last_name ?: ($user->last_name ?: ''),
                'return_url' => $returnUrl,
                'description' => "{$order->product_name} × {$order->quantity} — commande {$order->reference}",
            ]);

            $paymentUrl = $session['redirect_url'] ?? $session['redirectUrl'] ?? $session['payment_url'] ?? null;
            if (!empty($paymentUrl)) {
                $order->update([
                    'status' => 'pending_payment',
                    'payment_url' => $paymentUrl,
                    'payment_checkout_id' => $session['transaction_id'] ?? $order->payment_checkout_id,
                    'payment_intent_id' => $session['merchant_transaction_id'] ?? $order->payment_intent_id,
                    'notify_token' => $session['notify_token'] ?? $order->notify_token,
                ]);

                return Inertia::location($paymentUrl);
            }
        } catch (\Throwable $e) {
            \Log::warning("Reprise de paiement: échec de régénération session CinetPay ({$e->getMessage()}), tentative avec lien existant.");
        }

        if ($order->payment_url) {
            return Inertia::location($order->payment_url);
        }

        return redirect()->route('account.orders.show', $order->id)
            ->with('error', 'Impossible de reprendre le paiement. Veuillez contacter le support.');
    }
}
