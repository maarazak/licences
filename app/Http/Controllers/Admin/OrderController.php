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
    public function index(Request $request)
    {
        $status = $request->query('statut');
        $search = trim($request->query('q', ''));

        $query = Order::with('user', 'product')->orderBy('created_at', 'desc');

        if ($status === 'attente') {
            $query->where('status', 'pending_payment');
        } elseif ($status === 'preparation') {
            $query->whereIn('status', ['paid', 'processing', 'activation_awaiting', 'failed']);
        } elseif ($status === 'livrees') {
            $query->where('status', 'delivered');
        } elseif ($status === 'remboursees') {
            $query->where('status', 'refunded');
        } elseif ($status === 'annulees') {
            $query->where('status', 'cancelled');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders', 'status', 'search'));
    }

    public function show($id)
    {
        $order = Order::with('user', 'product', 'events')->findOrFail($id);

        return view('admin.orders.show', compact('order'));
    }

    public function saveNote(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->admin_note = $request->input('admin_note');
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'note_added',
            'message' => 'Note administrative mise à jour.',
            'actor' => auth()->user()->email,
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
                'actor' => auth()->user()->email,
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
            return back()->with('error', 'Cette commande est déjà remboursée.');
        }

        $order->status = 'refunded';
        $order->refund_method = 'gateway';
        $order->refunded_at = now();
        $order->save();

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'refunded_gateway',
            'message' => "Commande marquée comme remboursée sur le moyen de paiement d'origine ({$order->total_xof} FCFA).",
            'actor' => auth()->user()->email,
            'created_at' => now(),
        ]);

        AuditLog::record('order.refund_gateway', $order->id, [
            'amount_xof' => $order->total_xof,
            'reference' => $order->reference,
        ]);

        return back()->with('success', 'Commande marquée remboursée sur le moyen de paiement.');
    }

    public function syncPayment($id)
    {
        $order = Order::findOrFail($id);
        $fresh = app(\App\Services\OrderFulfillmentService::class)->syncPayment($order->id);

        AuditLog::record('order.sync_payment', $order->id, [
            'reference' => $order->reference,
            'status_after' => $fresh?->status,
        ]);

        return back()->with('success', "Paiement synchronisé auprès de pawaPay (statut : {$fresh?->status}).");
    }

    public function retryFulfilment($id)
    {
        $order = Order::findOrFail($id);
        app(\App\Services\OrderFulfillmentService::class)->fulfil($order->id);

        AuditLog::record('order.retry_fulfilment', $order->id, [
            'reference' => $order->reference,
        ]);

        return back()->with('success', "Livraison relancée pour la commande {$order->reference}.");
    }

    public function revealCredentials($id)
    {
        $order = Order::findOrFail($id);
        $credentials = null;
        if ($order->delivery_encrypted) {
            $credentials = \App\Services\CryptoService::decrypt($order->delivery_encrypted, $order->id);
        }

        AuditLog::record('order.reveal_credentials', $order->id, [
            'reference' => $order->reference,
        ]);

        return response()->json([
            'credentials' => $credentials,
        ]);
    }

    public function manualDelivery(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'credentials' => 'required|string|min:3',
        ]);

        $plain = trim($request->input('credentials'));
        $encrypted = \App\Services\CryptoService::encrypt($plain, $order->id);

        $order->update([
            'status' => 'delivered',
            'delivery_encrypted' => $encrypted,
            'delivered_at' => now(),
            'failure_message' => null,
            'fulfil_locked_at' => null,
        ]);

        OrderEvent::create([
            'id' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'type' => 'delivered_manual',
            'message' => 'Accès et identifiants livrés manuellement par l\'administrateur.',
            'actor' => auth()->user()->email,
            'created_at' => now(),
        ]);

        AuditLog::record('order.manual_delivery', $order->id, [
            'reference' => $order->reference,
        ]);

        try {
            app(\App\Services\MailService::class)->sendOrderDelivered($order);
        } catch (\Throwable $e) {}

        return back()->with('success', "Identifiants enregistrés et commande {$order->reference} marquée comme livrée !");
    }
}
