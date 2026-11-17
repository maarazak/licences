<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\AuditLog;
use App\Models\WalletEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public const ORDER_FILTERS = [
        'toutes' => 'Toutes',
        'a-traiter' => 'À traiter',
        'en-cours' => 'En cours',
        'livrees' => 'Livrées',
        'en-attente' => 'Paiement en attente',
        'closes' => 'Closes',
    ];

    public function index(Request $request)
    {
        $filtre = $request->query('filtre', $request->query('statut', 'toutes'));
        if (!array_key_exists($filtre, self::ORDER_FILTERS)) {
            $filtre = 'toutes';
        }

        $q = trim($request->query('q', ''));

        $query = Order::with('user', 'product')->orderBy('created_at', 'desc');

        if ($filtre === 'a-traiter') {
            $query->where('status', 'failed');
        } elseif ($filtre === 'en-cours') {
            $query->whereIn('status', ['paid', 'processing', 'activation_awaiting']);
        } elseif ($filtre === 'livrees') {
            $query->where('status', 'delivered');
        } elseif ($filtre === 'en-attente') {
            $query->where('status', 'pending_payment');
        } elseif ($filtre === 'closes') {
            $query->whereIn('status', ['payment_failed', 'expired', 'cancelled', 'refunded']);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhere('product_name', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('payment_intent_id', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($uq) use ($q) {
                        $uq->where('email', 'like', "%{$q}%");
                    });
            });
        }

        $orders = $query->paginate(30)->withQueryString();
        $filters = self::ORDER_FILTERS;

        return view('admin.orders.index', compact('orders', 'filtre', 'q', 'filters'));
    }

    public function show(Request $request, $id)
    {
        $order = Order::with('user', 'product', 'events')->findOrFail($id);

        $reveal = $request->query('acces') === '1' && $order->status === 'delivered';
        if ($reveal) {
            AuditLog::record('commande.voir-acces', $order->id, ['reference' => $order->reference]);
        }

        return view('admin.orders.show', compact('order', 'reveal'));
    }

    public function saveNote(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->admin_note = $request->input('note', $request->input('admin_note'));
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'note_added',
            'message' => 'Note administrative mise à jour.',
            'actor' => auth('admin')->user()->email ?? 'Admin',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Note enregistrée avec succès.');
    }

    public function refundBalance(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'refunded') {
            return back()->with('error', 'Cette commande est déjà remboursée.');
        }

        DB::transaction(function () use ($order) {
            $user = User::lockForUpdate()->find($order->user_id);
            if ($user) {
                $newBalance = $user->balance_xof + $order->total_xof;
                $user->balance_xof = $newBalance;
                $user->save();

                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => $order->total_xof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'credit',
                    'description' => "Remboursement sur solde — commande {$order->reference}",
                    'reference_type' => 'order_refund',
                    'reference_id' => $order->id,
                    'created_at' => now(),
                ]);
            }

            $order->status = 'refunded';
            $order->refund_method = 'balance';
            $order->refunded_at = now();
            $order->save();

            OrderEvent::create([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'type' => 'refunded',
                'message' => "Commande remboursée sur le solde du client ({$order->total_xof} FCFA).",
                'actor' => auth('admin')->user()->email ?? 'Admin',
                'created_at' => now(),
            ]);

            AuditLog::record('order.refund_balance', $order->id, [
                'amount_xof' => $order->total_xof,
                'reference' => $order->reference,
            ]);
        });

        return back()->with('success', "Le montant de {$order->total_xof} FCFA a été crédité sur le solde du client.");
    }

    public function refundGateway(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'refunded') {
            return back()->with('error', 'Cette commande est déjà marquée comme remboursée.');
        }

        $order->status = 'refunded';
        $order->refund_method = 'gateway';
        $order->refunded_at = now();
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'refunded',
            'message' => "Commande marquée comme remboursée auprès de la passerelle pawaPay.",
            'actor' => auth('admin')->user()->email ?? 'Admin',
            'created_at' => now(),
        ]);

        AuditLog::record('order.refund_gateway', $order->id, [
            'amount_xof' => $order->total_xof,
            'reference' => $order->reference,
        ]);

        return back()->with('success', 'Commande marquée comme remboursée auprès de la passerelle.');
    }

    public function markFailed($id)
    {
        $order = Order::findOrFail($id);

        $order->status = 'failed';
        $order->failure_code = 'manual_hold';
        $order->failure_message = "Mise en échec manuelle par l'administrateur.";
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'failed',
            'message' => "Commande mise en échec manuelle par l'administrateur.",
            'actor' => auth('admin')->user()->email ?? 'Admin',
            'created_at' => now(),
        ]);

        AuditLog::record('commande.mettre-en-echec', $order->id, ['reference' => $order->reference]);

        return back()->with('success', 'Commande marquée en échec.');
    }

    public function retryFulfilment($id)
    {
        $order = Order::findOrFail($id);

        try {
            app(\App\Services\FulfillmentService::class)->fulfill($order);
            return back()->with('success', 'Tentative de livraison relancée avec succès.');
        } catch (\Throwable $e) {
            return back()->with('error', "Échec de la relance fournisseur : " . $e->getMessage());
        }
    }

    public function syncPayment($id)
    {
        $order = Order::findOrFail($id);

        try {
            $updated = app(\App\Services\PawaPayService::class)->checkPaymentStatus($order);
            return back()->with('success', "Statut de paiement actualisé : {$order->status}.");
        } catch (\Throwable $e) {
            return back()->with('error', "Erreur lors de la vérification pawaPay : " . $e->getMessage());
        }
    }

    public function revealCredentials($id)
    {
        $order = Order::findOrFail($id);
        $plain = \App\Services\CryptoService::decrypt($order->delivery_encrypted, $order->id);

        AuditLog::record('order.reveal_credentials', $order->id, [
            'reference' => $order->reference,
        ]);

        return response()->json(['credentials' => $plain]);
    }

    public function manualDelivery(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'credentials' => 'required|string',
        ]);

        $order->delivery_encrypted = \App\Services\CryptoService::encrypt($validated['credentials'], $order->id);
        $order->status = 'delivered';
        $order->delivered_at = now();
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'delivered',
            'message' => 'Accès saisis et délivrés manuellement par un administrateur.',
            'actor' => auth('admin')->user()->email ?? 'Admin',
            'created_at' => now(),
        ]);

        AuditLog::record('order.manual_delivery', $order->id, [
            'reference' => $order->reference,
        ]);

        return back()->with('success', 'Accès délivrés manuellement avec succès.');
    }
}
