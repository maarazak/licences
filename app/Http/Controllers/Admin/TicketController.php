<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('statut');

        $query = Ticket::with('user', 'order')->orderBy('updated_at', 'desc');

        if ($status && $status !== 'tous') {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(20)->withQueryString();

        return view('admin.tickets.index', compact('tickets', 'status'));
    }

    public function show($id)
    {
        $ticket = Ticket::with(['user', 'order', 'messages.author'])->findOrFail($id);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string|min:2',
        ]);

        TicketMessage::create([
            'id' => Str::uuid()->toString(),
            'ticket_id' => $ticket->id,
            'author_id' => auth()->id(),
            'is_staff' => true,
            'body' => $validated['body'],
            'created_at' => now(),
        ]);

        $ticket->touch();

        return back()->with('success', 'Réponse envoyée au client.');
    }

    public function close($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->status = 'closed';
        $ticket->save();

        return back()->with('success', 'Ticket clôturé.');
    }
}
