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
        $search = trim($request->query('q', ''));

        $query = User::where('role', 'client')->orderBy('created_at', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(20)->withQueryString();

        return view('admin.clients.index', compact('clients', 'search'));
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

    public function adjustBalance(Request $request, $id)
    {
        $client = User::findOrFail($id);

        $validated = $request->validate([
            'amount_xof' => 'required|integer',
            'reason' => 'required|string|max:200',
        ]);

        $amount = $validated['amount_xof'];
        $newBalance = max(0, $client->balance_xof + $amount);

        $client->balance_xof = $newBalance;
        $client->save();

        WalletEntry::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $client->id,
            'amount_xof' => $amount,
            'balance_after_xof' => $newBalance,
            'type' => $amount >= 0 ? 'credit' : 'debit',
            'description' => "Ajustement administratif : {$validated['reason']}",
            'reference_type' => 'admin_adjustment',
            'reference_id' => auth()->id(),
            'created_at' => now(),
        ]);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'client.adjust_balance',
            'target_type' => 'user',
            'target_id' => $client->id,
            'meta' => ['amount' => $amount, 'reason' => $validated['reason']],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return back()->with('success', "Le solde a été mis à jour ({$newBalance} FCFA).");
    }

    public function toggleBlock($id)
    {
        $client = User::findOrFail($id);
        $client->is_blocked = !$client->is_blocked;
        $client->save();

        $action = $client->is_blocked ? 'bloqué' : 'débloqué';
        return back()->with('success', "Le client {$client->email} a été {$action}.");
    }
}
