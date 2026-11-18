<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->query('q', ''));

        $query = User::withCount([
            'orders as paid_orders_count' => function ($sq) {
                $sq->whereIn('status', ['paid', 'processing', 'activation_awaiting', 'delivered', 'failed']);
            }
        ])->withSum([
            'orders as delivered_spent' => function ($sq) {
                $sq->where('status', 'delivered');
            }
        ], 'total_xof')->orderBy('created_at', 'desc');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $clients = $query->paginate(30)->withQueryString();

        return view('admin.clients.index', compact('clients', 'q'));
    }

    public function show($id)
    {
        $client = User::with(['orders' => function ($q) {
            $q->orderBy('created_at', 'desc')->take(20);
        }, 'walletEntries' => function ($q) {
            $q->orderBy('created_at', 'desc')->take(20);
        }])->findOrFail($id);

        return view('admin.clients.show', compact('client'));
    }

    public function adjustBalance(Request $request, $id = null)
    {
        $userId = $id ?: $request->input('userId');

        $validated = $request->validate([
            'amount_xof' => 'required|numeric',
            'label' => 'nullable|string|max:200',
            'reason' => 'nullable|string|max:200',
        ]);

        $amount = (int) $validated['amount_xof'];
        $reason = $validated['label'] ?? $validated['reason'] ?? 'Ajustement administratif';

        $client = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $amount, $reason) {
            $user = User::lockForUpdate()->findOrFail($userId);
            $newBalance = max(0, $user->balance_xof + $amount);
            $user->balance_xof = $newBalance;
            $user->save();

            WalletEntry::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'amount_xof' => $amount,
                'balance_after_xof' => $newBalance,
                'type' => $amount >= 0 ? 'credit' : 'debit',
                'description' => "Ajustement administratif : {$reason}",
                'reference_type' => 'admin_adjustment',
                'reference_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);

            return $user;
        });

        AuditLog::record('client.adjust_balance', $client->id, [
            'amount' => $amount,
            'reason' => $reason,
        ]);

        return back()->with('success', "Le solde a été mis à jour ({$client->balance_xof} FCFA).");
    }

    public function toggleBlock(Request $request, $id = null)
    {
        $userId = $id ?: $request->input('userId');
        $client = User::findOrFail($userId);

        if ($request->has('blocked')) {
            $client->is_blocked = (bool) $request->input('blocked');
        } else {
            $client->is_blocked = !$client->is_blocked;
        }

        $client->save();

        $action = $client->is_blocked ? 'client.suspendre' : 'client.retablir';
        AuditLog::record($action, $client->id, ['email' => $client->email]);

        $statusMsg = $client->is_blocked ? 'Compte suspendu et déconnecté.' : 'Compte rétabli.';
        return back()->with('success', $statusMsg);
    }
}
