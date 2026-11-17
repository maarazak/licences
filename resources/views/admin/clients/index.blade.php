@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Clients</h1>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.clients.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-60">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="E-mail, nom ou téléphone" class="input">
        </div>
        <button type="submit" class="btn btn-soft">
            Rechercher
        </button>
        @if($q !== '')
            <a href="{{ route('admin.clients.index') }}" class="btn btn-soft text-muted">
                Effacer
            </a>
        @endif
    </form>

    @if($clients->count() > 0)
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Clients</caption>
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col" class="text-right">Commandes payées</th>
                        <th scope="col" class="text-right">Total livré</th>
                        <th scope="col" class="text-right">Solde</th>
                        <th scope="col">Inscrit le</th>
                        <th scope="col">Compte</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $user)
                        <tr>
                            <td>
                                <span class="font-medium [overflow-wrap:anywhere] text-ink">{{ $user->email }}</span>
                                <span class="block text-xs text-muted">
                                    {{ trim("{$user->first_name} {$user->last_name}") ?: '—' }}
                                    @if($user->phone) · {{ $user->phone }} @endif
                                </span>
                                @if($user->role === 'admin')
                                    <span class="badge badge-info mt-1">Administrateur</span>
                                @endif
                            </td>
                            <td class="text-right">
                                {{ (int) $user->paid_orders_count }}
                            </td>
                            <td class="text-right whitespace-nowrap font-medium">
                                {{ number_format((int) $user->delivered_spent, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="text-right">
                                <span class="font-semibold whitespace-nowrap text-ink">{{ number_format($user->balance_xof, 0, ',', ' ') }} FCFA</span>
                                <details class="mt-1 text-left">
                                    <summary class="cursor-pointer text-xs text-brand hover:underline">Ajuster</summary>
                                    <form action="{{ route('admin.clients.adjust_balance', $user->id) }}" method="POST" class="mt-2 w-56 space-y-2">
                                        @csrf
                                        <input type="hidden" name="userId" value="{{ $user->id }}">
                                        <input name="amount_xof" inputmode="numeric" required placeholder="Montant (+ ou −)" aria-label="Montant en FCFA" class="input text-xs py-1.5 min-h-8">
                                        <input name="label" required minlength="3" maxlength="200" placeholder="Motif" aria-label="Motif" class="input text-xs py-1.5 min-h-8">
                                        <button type="submit" class="btn btn-soft btn-sm w-full">Valider</button>
                                    </form>
                                </details>
                            </td>
                            <td class="whitespace-nowrap text-muted text-xs">
                                {{ $user->created_at ? $user->created_at->format('d/m/Y') : '—' }}
                            </td>
                            <td>
                                @if($user->is_blocked)
                                    <span class="badge badge-danger">Suspendu</span>
                                @else
                                    <span class="badge badge-success">Actif</span>
                                @endif

                                @if($user->role !== 'admin')
                                    <div class="mt-1.5">
                                        <form action="{{ route('admin.clients.toggle_block', $user->id) }}" method="POST" onsubmit="return confirm('{{ $user->is_blocked ? 'Rétablir ce compte ?' : 'Suspendre ce compte et le déconnecter ?' }}');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $user->is_blocked ? 'btn-soft' : 'btn-danger' }}">
                                                {{ $user->is_blocked ? 'Rétablir' : 'Suspendre' }}
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
            <div class="mt-4">
                {{ $clients->links() }}
            </div>
        @endif
    @else
        <div class="card px-6 py-10 text-center">
            <p class="h3">{{ $q ? 'Aucun client' : 'Aucun client' }}</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                {{ $q ? 'Aucun compte ne correspond à cette recherche.' : 'Les comptes apparaissent ici à la première connexion.' }}
            </p>
        </div>
    @endif
</div>
@endsection
