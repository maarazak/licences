<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\PawaPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderApiController extends Controller
{
    protected PawaPayService $pawaPay;

    public function __construct(PawaPayService $pawaPay)
    {
        $this->pawaPay = $pawaPay;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:50',
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:10',
            'pay_with' => 'required|in:gateway,balance',
            'activation_emails' => 'nullable|array',
            'activation_emails.*' => 'email',
            'return_url' => 'required|url',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (!$product->is_published || !$product->supplier_active) {
            return response()->json(['error' => 'Ce produit n\'est plus disponible à la vente.'], 400);
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            [
                'id' => Str::uuid()->toString(),
                'role' => 'client',
                'balance_xof' => 0,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? 'BF',
                'lang' => 'fr',
                'created_at' => now(),
            ]
        );

        $unitPrice = (int) $product->price_xof;
        $totalXof = $unitPrice * $validated['quantity'];
        $orderId = Str::uuid()->toString();
        $reference = 'CMD-' . strtoupper(Str::random(8));
        $usdRate = (int) \App\Models\Setting::get('usdRate', \App\Models\Setting::get('usd_rate', 669));

        // Paiement par solde
        if ($validated['pay_with'] === 'balance') {
            if ($user->balance_xof < $totalXof) {
                return response()->json(['error' => 'Solde insuffisant pour régler cette commande.'], 400);
            }

            return DB::transaction(function () use ($user, $product, $orderId, $reference, $totalXof, $unitPrice, $validated, $usdRate) {
                $user = User::lockForUpdate()->find($user->id);
                $newBalance = $user->balance_xof - $totalXof;
                $user->balance_xof = $newBalance;
                $user->save();

                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => -$totalXof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'debit',
                    'description' => "Achat commande {$reference} ({$product->name})",
                    'reference_type' => 'order_payment',
                    'reference_id' => $orderId,
                    'created_at' => now(),
                ]);

                $order = Order::create([
                    'id' => $orderId,
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'product_name' => $product->display_name ?: $product->name,
                    'supplier_product_id' => $product->supplier_id,
                    'quantity' => $validated['quantity'],
                    'unit_price_xof' => $unitPrice,
                    'total_xof' => $totalXof,
                    'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
                    'usd_rate' => $usdRate,
                    'customer_first_name' => $validated['first_name'] ?? null,
                    'customer_last_name' => $validated['last_name'] ?? null,
                    'customer_phone' => $validated['phone'] ?? null,
                    'customer_country' => $validated['country'] ?? null,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_with' => 'balance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'paid_with_balance',
                    'message' => "Commande payée intégralement par le solde ({$totalXof} FCFA).",
                    'actor' => $user->email,
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'payment_url' => null,
                ]);
            });
        }

        // Paiement par Mobile Money (pawaPay)
        $order = Order::create([
            'id' => $orderId,
            'reference' => $reference,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->display_name ?: $product->name,
            'supplier_product_id' => $product->supplier_id,
            'quantity' => $validated['quantity'],
            'unit_price_xof' => $unitPrice,
            'total_xof' => $totalXof,
            'unit_cost_usd_cents' => $product->cost_usd_cents ?? 0,
            'usd_rate' => $usdRate,
            'customer_first_name' => $validated['first_name'] ?? null,
            'customer_last_name' => $validated['last_name'] ?? null,
            'customer_phone' => $validated['phone'] ?? null,
            'customer_country' => $validated['country'] ?? 'BF',
            'status' => 'pending_payment',
            'paid_with' => 'gateway',
            'created_at' => now(),
            'updated_at' => now(),
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
            $checkout = $this->pawaPay->createPaymentPage([
                'deposit_id' => $order->id,
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
                'payment_checkout_id' => $checkout['deposit_id'],
                'payment_intent_id' => $checkout['deposit_id'],
                'payment_url' => $checkout['redirect_url'],
                'updated_at' => now(),
            ]);

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'checkout_created',
                'message' => 'Session de paiement pawaPay initialisée.',
                'actor' => 'pawapay',
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'payment_url' => $checkout['redirect_url'],
            ]);
        } catch (Exception $e) {
            Log::error("[OrderApiController] échec paiement pawaPay: {$e->getMessage()}");
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

        // Si l'URL actuelle est toujours valide
        if (PawaPayService::isPaymentUrlValid($order->payment_url)) {
            return response()->json(['redirect_url' => $order->payment_url]);
        }

        // Renouvellement pawaPay
        try {
            $checkout = $this->pawaPay->createPaymentPage([
                'deposit_id' => $order->id,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'amount_xof' => $order->total_xof,
                'description' => "{$order->product_name} — reprise paiement {$order->reference}",
                'email' => $order->user->email ?? 'client@example.com',
                'phone' => $order->customer_phone,
                'country' => $order->customer_country,
                'return_url' => $request->query('return_url', url("/paiement/retour?commande={$order->id}")),
            ]);

            $order->update([
                'payment_url' => $checkout['redirect_url'],
                'updated_at' => now(),
            ]);

            return response()->json(['redirect_url' => $checkout['redirect_url']]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Impossible de renouveler le lien de paiement.'], 500);
        }
    }
}
