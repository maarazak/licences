@extends('layouts.admin')

@section('content')
<div class="max-w-3xl space-y-6">
    <nav aria-label="Fil d'Ariane" class="text-xs text-muted">
        <a href="{{ route('admin.tickets.index') }}" class="hover:text-ink">
            Assistance
        </a>
        / <span class="text-ink">{{ $ticket->reference }}</span>
    </nav>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="h2">{{ $ticket->subject }}</h1>
        @php
            $tones = [
                'open' => 'warning',
                'answered' => 'info',
                'closed' => 'neutral',
            ];
            $labels = [
                'open' => 'En attente de réponse',
                'answered' => 'Répondu',
                'closed' => 'Clôturé',
            ];
            $tone = $tones[$ticket->status] ?? 'neutral';
            $lbl = $labels[$ticket->status] ?? $ticket->status;
        @endphp
        <span class="badge badge-{{ $tone }}">{{ $lbl }}</span>
    </div>

    <p class="text-sm text-muted">
        Client : <span class="text-ink [overflow-wrap:anywhere] font-medium">{{ $ticket->user->email ?? '—' }}</span>
        @if($ticket->order)
            · Commande : 
            <a href="{{ route('admin.orders.show', $ticket->order_id) }}" class="link">
                {{ $ticket->order->reference }}
            </a>
        @endif
    </p>

    <!-- Message Thread -->
    <div class="space-y-4">
        @foreach($ticket->messages as $msg)
            <div class="card p-4 space-y-2 {{ $msg->is_staff ? 'border-brand/40 bg-brand-soft/20' : 'bg-paper' }}">
                <div class="flex items-center justify-between text-xs text-muted">
                    <span class="font-semibold {{ $msg->is_staff ? 'text-brand' : 'text-ink' }}">
                        {{ $msg->is_staff ? 'Équipe Licences' : ($ticket->user->email ?? 'Client') }}
                    </span>
                    <span>{{ $msg->created_at ? $msg->created_at->format('d/m/Y H:i') : '' }}</span>
                </div>
                <div class="text-sm text-ink whitespace-pre-wrap leading-relaxed">
                    {{ $msg->body }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reply Form -->
    <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST" class="card space-y-4 p-5">
        @csrf
        <div>
            <label for="body" class="label">Répondre au client</label>
            <textarea id="body" name="body" required minlength="2" maxlength="4000" rows="5" class="input"></textarea>
            <p class="hint">Le client reçoit un e-mail l'invitant à lire la réponse dans son espace.</p>
        </div>
        <button type="submit" class="btn btn-brand">
            Envoyer la réponse
        </button>
    </form>

    <!-- Status Action Button -->
    <div>
        @if($ticket->status === 'closed')
            <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST">
                @csrf
                <input type="hidden" name="status" value="open">
                <button type="submit" class="btn btn-soft">
                    Rouvrir la demande
                </button>
            </form>
        @else
            <form action="{{ route('admin.tickets.status', $ticket->id) }}" method="POST" onsubmit="return confirm('Clôturer cette demande ?');">
                @csrf
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="btn btn-soft text-danger">
                    Clôturer la demande
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
