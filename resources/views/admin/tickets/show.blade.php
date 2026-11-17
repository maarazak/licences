@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tickets.index') }}" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition text-slate-500">
                <i data-lucide="arrow-left" class="size-4"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $ticket->subject }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">Ticket {{ $ticket->reference }} · Client : {{ $ticket->user->email ?? '—' }}</p>
            </div>
        </div>

        @if($ticket->status !== 'closed')
            <form action="{{ route('admin.tickets.close', $ticket->id) }}" method="POST">
                @csrf
                <button type="submit" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl transition">
                    Clôturer le ticket
                </button>
            </form>
        @endif
    </div>

    <!-- Fil de discussion -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Messages échangés</h2>

        <div class="space-y-4">
            @forelse($ticket->messages as $msg)
                <div class="p-4 rounded-2xl {{ $msg->is_staff ? 'bg-teal-50/70 border border-teal-100 ml-8' : 'bg-slate-50 border border-slate-200/70 mr-8' }}">
                    <div class="flex items-center justify-between mb-1.5 text-xs">
                        <strong class="{{ $msg->is_staff ? 'text-teal-900 font-semibold' : 'text-slate-800 font-semibold' }}">
                            {{ $msg->is_staff ? 'Équipe Support' : ($ticket->user->email ?? 'Client') }}
                        </strong>
                        <span class="text-slate-400">{{ $msg->created_at->format('d/m/Y à H:i') }}</span>
                    </div>
                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $msg->body }}</p>
                </div>
            @empty
                <p class="text-xs text-slate-400 py-4 text-center">Aucun message pour l'instant.</p>
            @endforelse
        </div>

        @if($ticket->status !== 'closed')
            <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST" class="pt-4 border-t border-slate-100 space-y-3">
                @csrf
                <textarea name="body" rows="4" placeholder="Rédigez votre réponse au client..." required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-xs rounded-xl shadow-md transition">
                        Envoyer la réponse
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
