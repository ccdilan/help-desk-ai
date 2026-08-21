<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tickets = Ticket::query()
            ->with('project')
            ->latest()
            ->get()
            ->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'project_name' => $ticket->project->name,
                'has_srs' => $ticket->generated_prd !== null,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
        ]);
    }
}
