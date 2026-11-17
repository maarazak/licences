<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletEntry;
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

        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $clientUser = Auth::guard('web')->user();

        return Inertia::render('Checkout/Validation', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'display_name' => $product->display_name,
                'category' => $product->category,
                'shop_category' => $product->shop_category,
                'image_url' => $product->image_url,
                'price_xof' => $product->price_xof,
                'in_stock' => (bool) $product->in_stock,
                'stock' => $product->stock,
                'preorder' => (bool) $product->preorder,
                'expire_days' => $product->expire_days,
                'warranty' => $product->warranty,
                'delivery_kind' => $product->delivery_kind,
                'requires_email' => (bool) $product->requires_email,
            ],
            'clientUser' => $clientUser ? [
                'id' => $clientUser->id,
                'email' => $clientUser->email,
                'balance_xof' => (int) $clientUser->balance_xof,
                'country' => $clientUser->country ?? 'BF',
                'phone' => $clientUser->phone ?? '',
            ] : null,
        ]);
    }

    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:30',
            'country' => 'required|string|max:5',
            'paid_with' => 'required|in:pawapay,balance',
            'custom_fields' => 'nullable|array',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $totalXof = $product->price_xof * $validated['quantity'];
        $email = strtolower(trim($validated['email']));

        // Find or create customer
        $clientUser = Auth::guard('web')->user();
        if (!$clientUser || strtolower($clientUser->email) !== $email) {
            $clientUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'id' => Str::uuid()->toString(),
                    'role' => 'client',
                    'balance_xof' => 0,
                    'country' => $validated['country'],
                    'phone' => $validated['phone'] ?? null,
                    'lang' => 'fr',
                    'is_blocked' => false,
                    'created_at' => now(),
                ]
            );

            // If not logged in, log them in
            if (!Auth::guard('web')->check()) {
                Auth::guard('web')->login($clientUser, true);
            }
        }

        if ($clientUser->is_blocked) {
            return back()->with('error', 'Votre compte est suspendu. Contactez le support.');
        }

        // Generate short reference e.g. CMD-XXXXXX
        $ref = 'CMD-' . strtoupper(Str::random(8));
        $orderId = Str::uuid()->toString();

        $firstName = $clientUser->first_name ?: ($clientUser->name ?: 'Client');
        $lastName = $clientUser->last_name ?: '';

        // 1. Paid with wallet balance
        if ($validated['paid_with'] === 'balance') {
            if ($clientUser->balance_xof < $totalXof) {
                return back()->with('error', 'Solde insuffisant pour régler cette commande.');
            }

            DB::transaction(function () use ($clientUser, $totalXof, $orderId, $product, $validated, $ref, $firstName, $lastName) {
                // Deduct balance
                $clientUser->decrement('balance_xof', $totalXof);

                // Wallet entry
                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $clientUser->id,
                    'type' => 'debit',
                    'kind' => 'purchase',
                    'amount_xof' => -$totalXof,
                    'balance_after_xof' => $clientUser->balance_xof,
                    'description' => "Paiement commande {$ref} ({$product->name})",
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'created_at' => now(),
                ]);

                // Create Order
                $order = Order::create([
                    'id' => $orderId,
                    'reference' => $ref,
                    'user_id' => $clientUser->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'supplier_product_id' => $product->supplier_id,
                    'quantity' => $validated['quantity'],
                    'unit_price_xof' => $product->price_xof,
                    'total_xof' => $totalXof,
                    'paid_with' => 'balance',
                    'status' => 'paid',
                    'customer_first_name' => $firstName,
                    'customer_last_name' => $lastName,
                    'customer_email' => $clientUser->email,
                    'customer_phone' => $validated['phone'] ?? null,
                    'customer_country' => $validated['country'],
                    'custom_fields' => $validated['custom_fields'] ?? null,
                    'paid_at' => now(),
                    'created_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'paid',
                    'event' => 'paid',
                    'actor' => 'client',
                    'message' => "Commande réglée avec succès par solde du compte ({$totalXof} FCFA).",
                    'created_at' => now(),
                ]);
            });

            // Déclenchement de la livraison automatique
            try {
                app(\App\Services\OrderFulfillmentService::class)->fulfil($orderId);
            } catch (\Throwable $e) {
                \Log::warning("Erreur déclenchement fulfillment solde: " . $e->getMessage());
            }

            return redirect()->route('account.orders.show', $orderId)->with('success', 'Commande validée et payée avec succès !');
        }

        // 2. Paid with Mobile Money (pawaPay)
        $depositId = Str::uuid()->toString();

        $order = Order::create([
            'id' => $orderId,
            'reference' => $ref,
            'user_id' => $clientUser->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'supplier_product_id' => $product->supplier_id,
            'quantity' => $validated['quantity'],
            'unit_price_xof' => $product->price_xof,
            'total_xof' => $totalXof,
            'paid_with' => 'pawapay',
            'status' => 'pending_payment',
            'payment_checkout_id' => $depositId,
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_email' => $clientUser->email,
            'customer_phone' => $validated['phone'] ?? null,
            'customer_country' => $validated['country'],
            'custom_fields' => $validated['custom_fields'] ?? null,
            'created_at' => now(),
        ]);

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'created',
            'event' => 'created',
            'actor' => 'client',
            'message' => "Commande initiée via Mobile Money pawaPay.",
            'created_at' => now(),
        ]);

        try {
            $pawaPay = app(PawaPayService::class);
            $returnUrl = url("/paiement/retour?commande={$order->id}");

            $session = $pawaPay->createPaymentPage([
                'deposit_id' => $depositId,
                'order_id' => $order->id,
                'reference' => $ref,
                'amount_xof' => $totalXof,
                'country' => $validated['country'],
                'phone' => $validated['phone'] ?? null,
                'email' => $clientUser->email,
                'return_url' => $returnUrl,
                'description' => "{$product->name} × {$validated['quantity']}",
            ]);

            $order->update(['payment_url' => $session['redirectUrl']]);

            return Inertia::location($session['redirectUrl']);
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
        $orderId = $request->query('commande');
        if (!$orderId) {
            return redirect()->route('shop.index');
        }

        $order = Order::findOrFail($orderId);

        // Sync with pawaPay if still pending
        if ($order->status === 'pending_payment' && $order->payment_checkout_id) {
            try {
                $pawaPay = app(PawaPayService::class);
                $deposit = $pawaPay->getDeposit($order->payment_checkout_id);
                if ($deposit && in_array(strtoupper($deposit['status'] ?? ''), ['COMPLETED', 'SUCCESS'])) {
                    $order->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    OrderEvent::create([
                        'id' => Str::uuid()->toString(),
                        'order_id' => $order->id,
                        'event' => 'paid',
                        'actor' => 'pawapay',
                        'message' => "Paiement Mobile Money confirmé par pawaPay.",
                        'created_at' => now(),
                    ]);

                    try {
                        app(\App\Services\OrderFulfillmentService::class)->fulfil($order->id);
                    } catch (\Throwable $e) {
                        \Log::warning("Erreur déclenchement fulfillment retour: " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("Erreur sync deposit retour : " . $e->getMessage());
            }
        }

        return Inertia::render('Checkout/Return', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'total_xof' => $order->total_xof,
                'status' => $order->status,
                'payment_url' => $order->payment_url,
            ],
        ]);
    }

    public function resumeOrder($id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'pending_payment') {
            return redirect()->route('account.orders.show', $order->id);
        }

        // Check if payment URL is still fresh
        if ($order->payment_url && PawaPayService::isPaymentUrlValid($order->payment_url)) {
            return redirect()->away($order->payment_url);
        }

        // Renew session
        try {
            $pawaPay = app(PawaPayService::class);
            $newDepositId = Str::uuid()->toString();
            $returnUrl = url("/paiement/retour?commande={$order->id}");

            $session = $pawaPay->createPaymentPage([
                'deposit_id' => $newDepositId,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_xof' => $order->total_xof,
                'country' => $order->customer_country ?? 'BF',
                'phone' => $order->customer_phone ?? null,
                'email' => $order->customer_email,
                'return_url' => $returnUrl,
                'description' => $order->product_name,
            ]);

            $order->update([
                'payment_checkout_id' => $newDepositId,
                'payment_url' => $session['redirectUrl'],
            ]);

            return redirect()->away($session['redirectUrl']);
        } catch (\Throwable $e) {
            return redirect()->route('account.orders.show', $order->id)->with('error', "Impossible de reprendre le paiement : " . $e->getMessage());
        }
    }
}
