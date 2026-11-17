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
use App\Services\PawaPayService;
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

        $duration = $product->duration_label ?: match (true) {
            $product->expire_days >= 365 => '1 an',
            $product->expire_days === 30 => '1 mois',
            $product->expire_days > 0 => "{$product->expire_days} jours",
            default => $product->warranty ?: 'Selon offre',
        };

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
                'phone' => $user->phone ?? '',
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
            'payWith' => 'required|in:gateway,balance,pawapay',
        ]);

        // Normalisation payWith
        $payWith = in_array($validated['payWith'], ['gateway', 'pawapay'], true) ? 'pawapay' : 'balance';

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
                    'usd_rate' => 669,
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

            return redirect()->route('account.orders.show', $orderId)->with('success', 'Commande validée et payée avec succès !');
        }

        // 2. Paiement via passerelle pawaPay (Mobile Money)
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
            'usd_rate' => 669,
            'paid_with' => 'pawapay',
            'status' => 'pending_payment',
            'payment_checkout_id' => $orderId,
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
            'message' => "Commande créée en attente de paiement Mobile Money ({$totalXof} FCFA).",
            'created_at' => $now,
        ]);

        try {
            $pawaPay = app(PawaPayService::class);
            $returnUrl = url("/paiement/retour?commande={$order->id}");

            $session = $pawaPay->createPaymentPage([
                'deposit_id' => $orderId,
                'order_id' => $orderId,
                'reference' => $ref,
                'amount_xof' => $totalXof,
                'country' => $country,
                'phone' => $phone,
                'email' => $user->email,
                'return_url' => $returnUrl,
                'description' => "{$order->product_name} × {$quantity} — commande {$ref}",
            ]);

            $paymentUrl = $session['redirect_url'] ?? $session['redirectUrl'] ?? $session['payment_url'] ?? null;
            if (empty($paymentUrl)) {
                throw new \Exception("L'URL de paiement retournée par le prestataire est introuvable.");
            }
            $order->update(['payment_url' => $paymentUrl]);

            return Inertia::location($paymentUrl);
        } catch (\Throwable $e) {
            \Log::error("Erreur pawaPay checkout : " . $e->getMessage());
            $order->update([
                'status' => 'payment_failed',
                'failure_message' => $e->getMessage(),
            ]);

            return back()->with('error', "Impossible d'initialiser le paiement : " . $e->getMessage());
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
                ->first();

            if ($walletDeposit) {
                if ($walletDeposit->status === 'pending') {
                    try {
                        $pawaPay = app(PawaPayService::class);
                        $state = $pawaPay->getDeposit($walletDeposit->payment_checkout_id ?: $walletDeposit->id);
                        if (in_array(strtoupper($state['status'] ?? ''), ['COMPLETED', 'SUCCESS'])) {
                            DB::transaction(function () use ($walletDeposit) {
                                $locked = WalletDeposit::lockForUpdate()->find($walletDeposit->id);
                                if ($locked && !$locked->credited_at) {
                                    $user = User::lockForUpdate()->find($locked->user_id);
                                    if ($user) {
                                        $user->increment('balance_xof', $locked->amount_xof);
                                        WalletEntry::create([
                                            'id' => Str::uuid()->toString(),
                                            'user_id' => $user->id,
                                            'amount_xof' => $locked->amount_xof,
                                            'balance_after_xof' => $user->balance_xof,
                                            'type' => 'credit',
                                            'kind' => 'deposit',
                                            'description' => "Recharge Mobile Money — {$locked->reference}",
                                            'reference_type' => 'wallet_deposit',
                                            'reference_id' => $locked->id,
                                            'created_at' => now(),
                                        ]);
                                    }
                                    $locked->update([
                                        'status' => 'completed',
                                        'paid_at' => now(),
                                        'credited_at' => now(),
                                    ]);
                                }
                            });
                        }
                    } catch (\Throwable $e) {
                        \Log::warning("Erreur sync deposit retour: " . $e->getMessage());
                    }
                }

                return redirect()->route('account.wallet')->with('success', 'Votre solde a été mis à jour.');
            }
        }

        // Cas commande de licence
        $targetOrder = $orderId ?: $depositId;
        if (!$targetOrder) {
            return redirect()->route('shop.index');
        }

        $order = Order::where('id', $targetOrder)
            ->orWhere('payment_checkout_id', $targetOrder)
            ->firstOrFail();

        // Relecture sécurisée auprès de pawaPay si le statut est encore en attente
        if ($order->status === 'pending_payment' && $order->payment_checkout_id) {
            try {
                $pawaPay = app(PawaPayService::class);
                $state = $pawaPay->getDeposit($order->payment_checkout_id);
                if (in_array(strtoupper($state['status'] ?? ''), ['COMPLETED', 'SUCCESS'])) {
                    $order->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    OrderEvent::create([
                        'id' => Str::uuid()->toString(),
                        'order_id' => $order->id,
                        'type' => 'paid',
                        'event' => 'paid',
                        'actor' => 'pawapay',
                        'message' => "Paiement confirmé lors du retour sur le site.",
                        'created_at' => now(),
                    ]);

                    try {
                        app(MailService::class)->sendOrderPaid($order);
                        app(OrderFulfillmentService::class)->fulfil($order->id);
                    } catch (\Throwable $e) {
                        \Log::warning("Erreur fulfillment post-retour: " . $e->getMessage());
                    }
                } elseif (in_array(strtoupper($state['status'] ?? ''), ['FAILED', 'EXPIRED', 'CANCELLED'])) {
                    $order->update([
                        'status' => 'payment_failed',
                        'failure_message' => $state['failure_reason'] ?? 'Paiement non finalisé.',
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning("Erreur vérification pawaPay lors du retour: " . $e->getMessage());
            }
        }

        return Inertia::render('Checkout/Return', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'total_xof' => (int) $order->total_xof,
                'status' => $order->status,
                'payment_url' => $order->payment_url,
            ],
        ]);
    }
}
