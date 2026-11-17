<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public const TICKET_STATUSES = [
        'open' => 'En attente de réponse',
        'answered' => 'Répondu',
        'closed' => 'Clôturé',
    ];

    public function index(Request $request)
    {
        $status = $request->query('statut');
        if ($status && !array_key_exists($status, self::TICKET_STATUSES)) {
            $status = null;
        }

        $query = Ticket::with('user', 'order')->orderBy('updated_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(20)->withQueryString();
        $statuses = self::TICKET_STATUSES;

        return view('admin.tickets.index', compact('tickets', 'status', 'statuses'));
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
            'body' => 'required|string|min:2|max:4000',
        ]);

        TicketMessage::create([
            'id' => Str::uuid()->toString(),
            'ticket_id' => $ticket->id,
            'author_id' => auth('admin')->id(),
            'is_staff' => true,
            'body' => $validated['body'],
            'created_at' => now(),
        ]);

        $ticket->status = 'answered';
        $ticket->touch();
        $ticket->save();

        AuditLog::record('ticket.reply', $ticket->id, [
            'reference' => $ticket->reference,
        ]);

        return back()->with('success', 'Réponse envoyée au client.');
    }

    public function close(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->status = 'closed';
        $ticket->save();

        AuditLog::record('ticket.close', $ticket->id, ['reference' => $ticket->reference]);

        return back()->with('success', 'Demande clôturée.');
    }

    public function setStatus(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);
        $newStatus = $request->input('status', 'closed');

        if (in_array($newStatus, ['open', 'answered', 'closed'])) {
            $ticket->status = $newStatus;
            $ticket->save();

            AuditLog::record('ticket.status_change', $ticket->id, [
                'status' => $newStatus,
                'reference' => $ticket->reference,
            ]);
        }

        $msg = $newStatus === 'open' ? 'Demande rouverte.' : 'Demande clôturée.';
        return back()->with('success', $msg);
    }
}
