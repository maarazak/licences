<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\CinetPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderApiController extends Controller
{
    protected CinetPayService $cinetPay;

    public function __construct(CinetPayService $cinetPay)
    {
        $this->cinetPay = $cinetPay;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:10',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:10',
            'pay_with' => 'required|in:cinetpay,balance,pawapay',
            'activation_emails' => 'nullable|array',
            'return_url' => 'required|url',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (!$product->is_available || $product->stock <= 0) {
            return response()->json(['error' => 'Ce produit est actuellement indisponible.'], 400);
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            [
                'id' => Str::uuid()->toString(),
                'role' => 'client',
                'balance_xof' => 0,
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? 'BF',
                'lang' => 'fr',
                'created_at' => now(),
            ]
        );

        $unitPriceXof = (int) $product->price_xof;
        $totalXof = $unitPriceXof * $validated['quantity'];
        $orderId = Str::uuid()->toString();
        $reference = 'CMD-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $payWith = in_array($validated['pay_with'], ['cinetpay', 'pawapay'], true) ? 'cinetpay' : 'balance';

        // 1. Paiement via Solde Client (Cash)
        if ($payWith === 'balance') {
            if ($user->balance_xof < $totalXof) {
                return response()->json([
                    'error' => 'Solde insuffisant.',
                    'balance_xof' => $user->balance_xof,
                    'required_xof' => $totalXof
                ], 400);
            }

            return DB::transaction(function () use ($user, $product, $validated, $totalXof, $unitPriceXof, $orderId, $reference) {
                $userLocked = User::lockForUpdate()->find($user->id);
                if ($userLocked->balance_xof < $totalXof) {
                    return response()->json(['error' => 'Solde insuffisant.'], 400);
                }

                $newBalance = $userLocked->balance_xof - $totalXof;
                $userLocked->balance_xof = $newBalance;
                $userLocked->save();

                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => -$totalXof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'debit',
                    'kind' => 'order_payment',
                    'description' => "Paiement commande {$product->name} x {$validated['quantity']}",
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'created_at' => now(),
                ]);

                $order = Order::create([
                    'id' => $orderId,
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $validated['quantity'],
                    'unit_price_xof' => $unitPriceXof,
                    'total_xof' => $totalXof,
                    'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
                    'usd_rate' => (int) \App\Models\Setting::get('usdRate', 669),
                    'paid_with' => 'balance',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'activation_emails' => $validated['activation_emails'] ?? null,
                    'created_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $orderId,
                    'type' => 'paid_balance',
                    'message' => "Paiement de {$totalXof} FCFA débité du solde client.",
                    'actor' => $user->email,
                    'created_at' => now(),
                ]);

                AuditLog::record('order.paid_balance', $orderId, [
                    'amount_xof' => $totalXof,
                    'new_balance' => $newBalance,
                ]);

                // Lancement de la livraison automatique en arrière-plan
                try {
                    app(\App\Services\MailService::class)->sendOrderPaid($order);
                    app(\App\Services\OrderFulfillmentService::class)->fulfil($orderId);
                } catch (\Throwable $e) {
                    Log::warning("[OrderApiController] Échec du déclenchement fulfillment: {$e->getMessage()}");
                }

                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'redirect_url' => "/compte/achats/{$order->id}",
                ]);
            });
        }

        // 2. Paiement par CinetPay (Cartes Bancaires + Mobile Money)
        $order = Order::create([
            'id' => $orderId,
            'reference' => $reference,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $validated['quantity'],
            'unit_price_xof' => $unitPriceXof,
            'total_xof' => $totalXof,
            'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
            'usd_rate' => (int) \App\Models\Setting::get('usdRate', 669),
            'paid_with' => 'cinetpay',
            'status' => 'pending_payment',
            'customer_email' => $user->email,
            'customer_phone' => $validated['phone'] ?? null,
            'customer_country' => $validated['country'] ?? 'BF',
            'activation_emails' => $validated['activation_emails'] ?? null,
            'payment_intent_id' => $reference,
            'created_at' => now(),
        ]);

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'created',
            'message' => "Commande créée pour un montant de {$totalXof} FCFA.",
            'actor' => $user->email,
            'created_at' => now(),
        ]);

        try {
            $checkout = $this->cinetPay->createPaymentPage([
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_xof' => $totalXof,
                'description' => "{$product->name} x {$validated['quantity']} — commande {$reference}",
                'email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? 'BF',
                'return_url' => $validated['return_url'],
            ]);

            $order->update([
                'payment_checkout_id' => $checkout['transaction_id'] ?? null,
                'payment_intent_id' => $checkout['merchant_transaction_id'] ?? $reference,
                'notify_token' => $checkout['notify_token'] ?? null,
                'payment_url' => $checkout['redirect_url'],
                'updated_at' => now(),
            ]);

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'checkout_created',
                'message' => 'Session de paiement CinetPay initialisée.',
                'actor' => 'cinetpay',
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'payment_url' => $checkout['redirect_url'],
            ]);
        } catch (Exception $e) {
            Log::error("[OrderApiController] échec paiement CinetPay: {$e->getMessage()}");
            $order->update([
                'status' => 'cancelled',
                'failure_code' => 'checkout_failed',
                'failure_message' => substr($e->getMessage(), 0, 300),
                'updated_at' => now(),
            ]);

            return response()->json([
                'error' => "Le paiement n'a pas pu être initialisé. Aucun montant n'a été débité. Réessayez dans un instant."
            ], 500);
        }
    }

    public function show($id)
    {
        $order = Order::with('product')->findOrFail($id);

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    public function resume($id, Request $request)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'pending_payment') {
            return response()->json(['redirect_url' => "/compte/achats/{$order->id}"]);
        }

        // Si l'URL actuelle est disponible
        if (!empty($order->payment_url)) {
            return response()->json(['redirect_url' => $order->payment_url]);
        }

        // Renouvellement CinetPay
        try {
            $checkout = $this->cinetPay->createPaymentPage([
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_xof' => $order->total_xof,
                'description' => "{$order->product_name} — reprise paiement {$order->reference}",
                'email' => $order->customer_email ?: ($order->user->email ?? 'client@example.com'),
                'phone' => $order->customer_phone,
                'country' => $order->customer_country,
                'return_url' => $request->query('return_url', url("/paiement/retour?commande={$order->id}")),
            ]);

            $order->update([
                'payment_url' => $checkout['redirect_url'],
                'payment_checkout_id' => $checkout['transaction_id'] ?? null,
                'payment_intent_id' => $checkout['merchant_transaction_id'] ?? $order->reference,
                'notify_token' => $checkout['notify_token'] ?? null,
                'updated_at' => now(),
            ]);

            return response()->json(['redirect_url' => $checkout['redirect_url']]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Impossible de renouveler le lien de paiement.'], 500);
        }
    }
}
